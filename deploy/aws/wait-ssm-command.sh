#!/usr/bin/env bash
set -euo pipefail

command_id=${1:?Usage: wait-ssm-command.sh COMMAND_ID INSTANCE_ID}
instance_id=${2:?Usage: wait-ssm-command.sh COMMAND_ID INSTANCE_ID}
# Allow the 600-second delivery window plus 3600 seconds of execution.
timeout_seconds=${SSM_WAIT_TIMEOUT_SECONDS:-4200}
poll_seconds=${SSM_POLL_SECONDS:-10}
deadline=$((SECONDS + timeout_seconds))
error_file=$(mktemp)
trap 'rm -f -- "$error_file"' EXIT
export AWS_PAGER=''

show_result() {
    aws ssm get-command-invocation \
        --command-id "$command_id" \
        --instance-id "$instance_id" \
        --query '{Status:Status,StatusDetails:StatusDetails,ResponseCode:ResponseCode,StandardOutput:StandardOutputContent,StandardError:StandardErrorContent}'
}

while true; do
    if status=$(aws ssm get-command-invocation \
        --command-id "$command_id" \
        --instance-id "$instance_id" \
        --query Status --output text 2>"$error_file"); then
        echo "SSM command $command_id: $status (${SECONDS}s elapsed)"
        case "$status" in
            Success)
                show_result
                exit 0
                ;;
            Pending|InProgress|Delayed|Cancelling) ;;
            *)
                show_result
                echo "::error::SSM command $command_id ended with status $status."
                exit 1
                ;;
        esac
    elif grep -q 'InvocationDoesNotExist' "$error_file"; then
        # SSM can take a few seconds to expose a newly submitted invocation.
        echo "Waiting for SSM invocation $command_id to become available..."
    else
        cat "$error_file" >&2
        exit 1
    fi

    if (( SECONDS >= deadline )); then
        show_result || true
        echo "::error::Timed out waiting for SSM command $command_id after ${timeout_seconds}s. The remote command may still be running; check it before redeploying."
        exit 1
    fi
    sleep "$poll_seconds"
done
