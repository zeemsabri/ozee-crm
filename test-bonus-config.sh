#!/bin/bash

# Generate a UUID
UUID=$(uuidgen)

# Create a JSON payload with both id and uuid fields
PAYLOAD='{
    "id": "'$UUID'",
    "uuid": "'$UUID'",
    "name": "Daily Standup Bonus",
    "type": "bonus",
    "amountType": "percentage",
    "value": 1,
    "appliesTo": "standup",
    "targetBonusTypeForRevocation": "",
    "isActive": true
}'

echo "Sending payload: $PAYLOAD"

# Make the POST request
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "$PAYLOAD" \
  http://localhost:8000/api/bonus-configurations

echo ""
echo "Request completed."
