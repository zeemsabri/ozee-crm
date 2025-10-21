# Nested Object Fields Support Fix

## Problem Description

The DataTokenInserter component in workflow steps was only showing top-level fields from AI Response structures, but not nested fields within objects. For example:

### Expected AI Response Structure:
```
subject: Text
ai_content: Object
  paragraphs: Array
  call_to_action: Object
    link: Text
    text: Text
  greeting: Text
summary: Object
  context_summary: Text
  reason: Text
action: Text
```

### What Users Could See Before the Fix:
- ✅ `subject`
- ✅ `ai_content` (but not `paragraphs`, `call_to_action`, `greeting`)
- ✅ `summary` (but not `context_summary`, `reason`)
- ✅ `action`

### What Users Wanted to See:
- ✅ All top-level fields
- ✅ **Nested object fields** like `context_summary` and `reason` from `summary`
- ✅ **Nested fields from complex objects** like `greeting` from `ai_content`

## Root Cause Analysis

The issue was in `/resources/js/Pages/Automation/Components/Steps/DataTokenInserter.vue`:

1. **Limited Nested Field Support**: The original code only handled nested fields for "Array of Objects" types
2. **Missing Regular Object Support**: Regular `Object` type fields with nested `schema` were ignored
3. **No Recursive Processing**: The code wasn't recursively processing nested object structures

### Original Code Limitations:
```javascript
// Only handled Array of Objects
if (isArrayOfObjects(field) && Array.isArray(field.schema)) {
    field.schema.forEach(sub => {
        // Show nested fields for arrays only
    });
}
// Missing: Regular Object type support!
```

## Solution Implemented

### 1. Added Recursive Field Processing

Implemented a recursive function `addFieldsRecursively` that:
- Processes all field types (Objects, Arrays, Array of Objects)
- Maintains proper path construction (`parent.child.grandchild`)
- Provides visual hierarchy with indentation
- Handles both simple and complex nested structures

### 2. Enhanced Field Type Detection

Now properly handles:
- **Regular Objects**: `type: 'Object'` with nested `schema`
- **Array of Objects**: `type: 'Array'` with `itemType: 'Object'`
- **Mixed Structures**: Objects containing arrays containing objects

### 3. Improved Token Path Generation

Generates correct token paths for all nesting levels:
- Top-level: `{{step_123.summary}}`
- Nested: `{{step_123.summary.context_summary}}`
- Deep nested: `{{step_123.ai_content.call_to_action.link}}`

### 4. Visual Hierarchy in Token Picker

Provides clear visual hierarchy with indentation:
```
Step 2: AI Response
  subject
  ai_content
    greeting
    paragraphs
    call_to_action
      link
      text
  summary
    context_summary
    reason
  action
```

## Technical Implementation

### 1. Updated DataTokenInserter.vue Logic:

```javascript
const addFieldsRecursively = (fieldsList, parentPath = '', indentLevel = 0) => {
    (fieldsList || []).forEach(field => {
        if (!field?.name) return;
        
        const currentPath = parentPath ? `${parentPath}.${field.name}` : field.name;
        const indent = '  '.repeat(indentLevel);
        
        // Always include the current field
        fields.push({
            label: `${indent}${field.name}`,
            value: `{{step_${step.id}.${currentPath}}}`
        });
        
        // Handle nested fields based on field type
        if (Array.isArray(field.schema)) {
            if (isArrayOfObjects(field)) {
                // Array of Objects: show nested fields with array access notation
                field.schema.forEach(sub => {
                    if (!sub?.name) return;
                    fields.push({
                        label: `${indent}  - ${sub.name} (from array item)`,
                        value: `{{step_${step.id}.${currentPath}.${sub.name}}}`
                    });
                });
            } else if (field.type === 'Object') {
                // Regular Object: show nested fields with direct access
                addFieldsRecursively(field.schema, currentPath, indentLevel + 1);
            }
        }
    });
};
```

### 2. Updated ConditionStep.vue Logic:

The ConditionStep component had the same issue - its "Select field" dropdown was only showing top-level AI response fields. Applied the same recursive processing logic:

```javascript
// In ConditionStep.vue availableFields computed property
if (s.step_type === 'AI_PROMPT' && s.step_config?.responseStructure?.length > 0) {
    // Helper function to check if field is Array of Objects
    const isArrayOfObjects = (field) => {
        const t = String(field?.type || '').toLowerCase();
        const it = String(field?.itemType || '').toLowerCase();
        return t === 'array of objects' || (t === 'array' && it === 'object');
    };
    
    // Recursive function to add fields and their nested children
    const addFieldsRecursively = (fieldsList, parentPath = '', indentLevel = 0) => {
        (fieldsList || []).forEach(field => {
            if (!field?.name) return;
            
            const currentPath = parentPath ? `${parentPath}.${field.name}` : field.name;
            const indent = '  '.repeat(indentLevel);
            
            // Always add the current field
            fields.push({
                value: `step_${s.id}.${currentPath}`,
                name: `step_${s.id}.${currentPath}`,
                label: `Step ${index + 1} (AI): ${indent}${field.name}`,
                type: field.type,
                group: `Step ${index + 1}: ${s.name}`,
                allowed_values: field.allowed_values || null,
            });
            
            // Handle nested fields based on field type
            if (Array.isArray(field.schema)) {
                if (isArrayOfObjects(field)) {
                    // Array of Objects: show nested fields with array access notation
                    field.schema.forEach(sub => {
                        if (!sub?.name) return;
                        fields.push({
                            value: `step_${s.id}.${currentPath}.${sub.name}`,
                            name: `step_${s.id}.${currentPath}.${sub.name}`,
                            label: `Step ${index + 1} (AI): ${indent}  - ${sub.name} (from array)`,
                            type: sub.type || 'Text',
                            group: `Step ${index + 1}: ${s.name}`,
                            allowed_values: sub.allowed_values || null,
                        });
                    });
                } else if (field.type === 'Object') {
                    // Regular Object: show nested fields with direct access
                    addFieldsRecursively(field.schema, currentPath, indentLevel + 1);
                }
            }
        });
    };
    
    addFieldsRecursively(s.step_config.responseStructure);
}
```

### Components Fixed:

1. **DataTokenInserter.vue** - Token picker in action steps (Send Email, Update Record, etc.)
2. **ConditionStep.vue** - "Select field" dropdown in condition steps

## Benefits

### ✅ **Complete Field Visibility**
- All nested fields are now visible in token picker
- No more manual typing of nested paths
- Proper path validation through UI selection

### ✅ **Visual Hierarchy**  
- Clear indentation shows field relationships
- Easy to understand parent-child structure
- Distinguishes between objects and array items

### ✅ **Correct Token Paths**
- Automatically generates proper dot notation paths
- Prevents syntax errors in token expressions
- Works with workflow engine's template resolution

### ✅ **Backward Compatibility**
- Existing workflows continue to work unchanged
- Array of Objects support remains intact
- No breaking changes to existing functionality

## Usage Examples

### Before Fix:
Users had to manually type:
```
{{step_212.summary.context_summary}}
{{step_212.ai_content.greeting}}
```

### After Fix:
Users can select from dropdown:
- `Step 2: AI Response > summary > context_summary`
- `Step 2: AI Response > ai_content > greeting`

## Testing

### Test Case 1: Simple Nested Object
AI Response Structure:
```json
[
  {
    "name": "summary",
    "type": "Object", 
    "schema": [
      {"name": "context_summary", "type": "Text"},
      {"name": "reason", "type": "Text"}
    ]
  }
]
```

Expected Token Options:
- `{{step_X.summary}}`
- `{{step_X.summary.context_summary}}`
- `{{step_X.summary.reason}}`

### Test Case 2: Complex Nested Structure  
AI Response Structure:
```json
[
  {
    "name": "ai_content",
    "type": "Object",
    "schema": [
      {"name": "greeting", "type": "Text"},
      {
        "name": "call_to_action", 
        "type": "Object",
        "schema": [
          {"name": "link", "type": "Text"},
          {"name": "text", "type": "Text"}
        ]
      }
    ]
  }
]
```

Expected Token Options:
- `{{step_X.ai_content}}`
- `{{step_X.ai_content.greeting}}`
- `{{step_X.ai_content.call_to_action}}`
- `{{step_X.ai_content.call_to_action.link}}`
- `{{step_X.ai_content.call_to_action.text}}`

## Migration Notes

- **No Action Required**: Existing workflows automatically benefit from enhanced field visibility
- **Improved UX**: Users can now see and select previously hidden nested fields
- **Same Token Syntax**: Generated tokens use the same `{{step_X.path}}` format

The fix resolves the "nested fields not showing" issue while maintaining full backward compatibility and providing an enhanced user experience for complex AI response structures.