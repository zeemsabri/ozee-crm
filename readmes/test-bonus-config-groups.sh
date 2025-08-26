#!/bin/bash

# Test script to verify the bonus configuration group API endpoints

# Set the base URL
BASE_URL="http://localhost:8000/api"

# Set the auth token (replace with a valid token)
AUTH_TOKEN="YOUR_AUTH_TOKEN"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}Testing Bonus Configuration Group API Endpoints${NC}"
echo "=================================================="

# 1. Create a bonus configuration
echo -e "\n${BLUE}1. Creating a bonus configuration...${NC}"
UUID=$(uuidgen)
CONFIG_PAYLOAD='{
    "id": "'$UUID'",
    "uuid": "'$UUID'",
    "name": "Test Bonus",
    "type": "bonus",
    "amountType": "percentage",
    "value": 5,
    "appliesTo": "task",
    "targetBonusTypeForRevocation": "",
    "isActive": true
}'

CONFIG_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -d "$CONFIG_PAYLOAD" \
  $BASE_URL/bonus-configurations)

echo "Response: $CONFIG_RESPONSE"
CONFIG_ID=$(echo $CONFIG_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$CONFIG_ID" ]; then
    echo -e "${RED}Failed to create bonus configuration${NC}"
    exit 1
else
    echo -e "${GREEN}Created bonus configuration with ID: $CONFIG_ID${NC}"
fi

# 2. Create a bonus configuration group
echo -e "\n${BLUE}2. Creating a bonus configuration group...${NC}"
GROUP_PAYLOAD='{
    "name": "Test Group",
    "description": "A test group for bonus configurations",
    "is_active": true,
    "configurations": ['$CONFIG_ID']
}'

GROUP_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -d "$GROUP_PAYLOAD" \
  $BASE_URL/bonus-configuration-groups)

echo "Response: $GROUP_RESPONSE"
GROUP_ID=$(echo $GROUP_RESPONSE | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

if [ -z "$GROUP_ID" ]; then
    echo -e "${RED}Failed to create bonus configuration group${NC}"
    exit 1
else
    echo -e "${GREEN}Created bonus configuration group with ID: $GROUP_ID${NC}"
fi

# 3. Get all bonus configuration groups
echo -e "\n${BLUE}3. Getting all bonus configuration groups...${NC}"
curl -s -X GET \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  $BASE_URL/bonus-configuration-groups | jq

# 4. Get a specific bonus configuration group
echo -e "\n${BLUE}4. Getting bonus configuration group with ID: $GROUP_ID...${NC}"
curl -s -X GET \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  $BASE_URL/bonus-configuration-groups/$GROUP_ID | jq

# 5. Update a bonus configuration group
echo -e "\n${BLUE}5. Updating bonus configuration group...${NC}"
UPDATE_PAYLOAD='{
    "name": "Updated Test Group",
    "description": "An updated test group for bonus configurations",
    "is_active": true,
    "configurations": ['$CONFIG_ID']
}'

curl -s -X PUT \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -d "$UPDATE_PAYLOAD" \
  $BASE_URL/bonus-configuration-groups/$GROUP_ID | jq

# 6. Duplicate a bonus configuration group
echo -e "\n${BLUE}6. Duplicating bonus configuration group...${NC}"
curl -s -X POST \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  $BASE_URL/bonus-configuration-groups/$GROUP_ID/duplicate | jq

# 7. Attach a bonus configuration group to a project
echo -e "\n${BLUE}7. Attaching bonus configuration group to a project...${NC}"
# Replace PROJECT_ID with a valid project ID
PROJECT_ID="1"
ATTACH_PAYLOAD='{
    "group_id": '$GROUP_ID'
}'

curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -d "$ATTACH_PAYLOAD" \
  $BASE_URL/projects/$PROJECT_ID/attach-bonus-configuration-group | jq

# 8. Detach a bonus configuration group from a project
echo -e "\n${BLUE}8. Detaching bonus configuration group from a project...${NC}"
DETACH_PAYLOAD='{
    "group_id": '$GROUP_ID'
}'

curl -s -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  -d "$DETACH_PAYLOAD" \
  $BASE_URL/projects/$PROJECT_ID/detach-bonus-configuration-group | jq

# 9. Delete a bonus configuration group
echo -e "\n${BLUE}9. Deleting bonus configuration group...${NC}"
curl -s -X DELETE \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $AUTH_TOKEN" \
  $BASE_URL/bonus-configuration-groups/$GROUP_ID | jq

echo -e "\n${GREEN}Test completed!${NC}"
