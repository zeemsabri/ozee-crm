import React, { useState, useMemo, useEffect } from 'react';

// --- MOCK DATA (Updated for a fully dynamic, context-aware system) ---
const MOCK_DATA = {
    // 1. More trigger events added
    triggers: {
        'Email': ['is received', 'is created', 'is updated'],
        'Task': ['is created', 'is completed', 'status is changed', 'is assigned to someone', 'is updated'],
        'Project': ['is created', 'is completed', 'is archived'],
    },
    // 2. Schemas define the "shape" of data for each object
    schemas: {
        'Email': {
            properties: [
                { name: 'subject', label: 'Subject', type: 'Text' },
                { name: 'body', label: 'Body', type: 'Text' },
                { name: 'sender', label: 'Sender Address', type: 'Text' },
                { name: 'receivedAt', label: 'Received Date', type: 'Date' },
            ]
        },
        'Task': {
            properties: [
                { name: 'name', label: 'Task Name', type: 'Text' },
                { name: 'status', label: 'Status', type: 'Select', options: ['To Do', 'In Progress', 'In Review', 'Done'] },
                { name: 'assignee', label: 'Assignee', type: 'User' },
                { name: 'dueDate', label: 'Due Date', type: 'Date' },
                { name: 'completedAt', label: 'Completed Date', type: 'Date' },
                { name: 'milestone', label: 'Milestone', type: 'Text' }, // Simplified for UI
            ]
        },
        'Project': {
            properties: [
                { name: 'name', label: 'Project Name', type: 'Text' },
                { name: 'status', label: 'Status', type: 'Select', options: ['Active', 'On Hold', 'Completed'] },
            ]
        }
    },
    stepTypes: {
        'ai': { name: 'Analyze with AI', icon: '🧠', description: 'Make a decision or extract information.' },
        'condition': { name: 'If/Else Condition', icon: '🔀', description: 'Split the workflow based on a rule.' },
        'action': { name: 'Perform an Action', icon: '⚙️', description: 'Send an email, create a task, etc.' },
    },
    // 3. Data types and their corresponding operators
    dataTypes: {
        'Text': { operators: ['is', 'is not', 'contains', 'does not contain'] },
        'Number': { operators: ['equals', 'is not equal to', 'is greater than', 'is less than'] },
        'True/False': { operators: ['is true', 'is false'] },
        'Date': { operators: ['is after', 'is before', 'is on'] },
        'User': { operators: ['is', 'is not'] },
        'Select': { operators: ['is', 'is not'] }
    },
    actions: {
        'Milestones': {
            types: ['Check for Completion & Update'],
            icon: '🏁'
        },
        'Email': {
            types: ['Create Draft Email', 'Send Email', 'Wait for Manual Approval'],
            icon: '📧'
        },
        'Notifications': {
            types: ['Post a project note', 'Send an in-app alert'],
            icon: '🔔'
        },
        'Tasks': {
            types: ['Create a sub-task', 'Change task status', 'Assign task to user'],
            icon: '✅'
        },
    },
    templates: [
        { icon: '🏁', title: 'Automate milestone completion', description: 'When a task is completed, check if it was the last task in a milestone and complete the milestone automatically.'},
        { icon: '🎉', title: 'Celebrate completed tasks', description: 'When a task is marked Done, post a celebration message in the project notes.' },
        { icon: '🔔', title: 'Notify on late tasks', description: 'If a task is completed after its milestone due date, alert the project manager.' },
        { icon: '🤖', title: 'Triage incoming support emails', description: 'When a new email is received, use AI to determine its category and create a task for the right team.' },
    ],
    myAutomations: [
        { name: 'Notify me about my overdue tasks', lastRan: '5 minutes ago', status: 'ok', active: true },
        { name: 'Triage support emails', lastRan: '15 minutes ago', status: 'ok', active: true},
        { name: 'Auto-complete Milestones', lastRan: '1 hour ago', status: 'ok', active: true },
        { name: 'Weekly Project Summary', lastRan: '3 days ago', status: 'ok', active: true },
        { name: 'Client Update on Milestone', lastRan: '1 day ago', status: 'error', active: false },
    ]
};

// --- SVG ICONS ---
const PlusIcon = ({size = 20}) => <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>;
const ArrowRightIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>;
const CheckCircleIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-green-500"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>;
const XCircleIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-red-500"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>;
const TrashIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>;


// --- HELPER COMPONENTS ---
const Card = ({ children, className }) => <div className={`bg-white rounded-lg shadow-md p-6 border border-gray-100 ${className}`}>{children}</div>;
const Button = ({ children, onClick, variant = 'primary', className = '', disabled = false }) => {
    const baseClasses = 'px-4 py-2 rounded-md font-semibold text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed';
    const variantClasses = {
        primary: 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 disabled:bg-indigo-300',
        secondary: 'bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-400 disabled:bg-gray-200',
        ghost: 'bg-transparent text-gray-600 hover:bg-gray-100',
    };
    return <button onClick={onClick} disabled={disabled} className={`${baseClasses} ${variantClasses[variant]} ${className}`}>{children}</button>;
};

// --- WORKFLOW STEP COMPONENTS ---

const StepCard = ({ icon, title, children, onDelete }) => (
    <div className="bg-white rounded-lg shadow-md border border-gray-200 w-full max-w-md">
        <div className="flex items-center justify-between p-3 bg-gray-50 border-b rounded-t-lg">
            <div className="flex items-center space-x-2">
                <span className="text-xl">{icon}</span>
                <h3 className="font-bold text-gray-700">{title}</h3>
            </div>
            {onDelete && <button onClick={onDelete} className="text-gray-400 hover:text-red-500"><TrashIcon /></button>}
        </div>
        <div className="p-4 space-y-4">
            {children}
        </div>
    </div>
);

const TriggerStep = ({ step, updateStep }) => {
    const handleObjectChange = (object) => {
        updateStep({ ...step, object, event: null });
    };
    const handleEventChange = (event) => {
        updateStep({ ...step, event });
    };
    return (
        <StepCard icon="⚡" title="1. When this happens... (Trigger)">
            <div className="flex items-center space-x-2 text-md">
                <span className="font-semibold text-gray-700">When a</span>
                <select value={step.object || ''} onChange={e => handleObjectChange(e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="" disabled>Select...</option>
                    {Object.keys(MOCK_DATA.triggers).map(obj => <option key={obj} value={obj}>{obj}</option>)}
                </select>
                {step.object && (
                    <select value={step.event || ''} onChange={e => handleEventChange(e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                        <option value="" disabled>is...</option>
                        {MOCK_DATA.triggers[step.object].map(evt => <option key={evt} value={evt}>{evt}</option>)}
                    </select>
                )}
            </div>
        </StepCard>
    );
};

const AIStep = ({ step, updateStep, onDelete, stepsBefore = [] }) => {
    const responseStructure = step.responseStructure || [];
    const aiInputs = step.aiInputs || [];

    const trigger = stepsBefore.find(s => s.type === 'trigger');
    const triggerSchema = trigger ? MOCK_DATA.schemas[trigger.object] : null;

    const handleAddField = () => {
        const newField = { id: Date.now(), name: '', type: 'Text' };
        updateStep({ ...step, responseStructure: [...responseStructure, newField] });
    };

    const handleUpdateField = (id, key, value) => {
        const newStructure = responseStructure.map(field =>
            field.id === id ? { ...field, [key]: value } : field
        );
        updateStep({ ...step, responseStructure: newStructure });
    };

    const handleDeleteField = (id) => {
        const newStructure = responseStructure.filter(field => field.id !== id);
        updateStep({ ...step, responseStructure: newStructure });
    };

    const handleAddInput = (fieldName) => {
        if (fieldName && !aiInputs.includes(fieldName)) {
            updateStep({ ...step, aiInputs: [...aiInputs, fieldName] });
        }
    };

    const handleRemoveInput = (fieldName) => {
        updateStep({ ...step, aiInputs: aiInputs.filter(i => i !== fieldName) });
    };

    const generatedJsonPrompt = useMemo(() => {
        if(responseStructure.length === 0) return "";
        const fields = responseStructure
            .filter(f => f.name)
            .map(f => `"${f.name}": <${f.type.toLowerCase()}>`)
            .join(', ');
        return `Respond with JSON in this exact format: { ${fields} }`;
    }, [responseStructure]);

    return (
        <StepCard icon="🧠" title="Analyze with AI" onDelete={onDelete}>
            <div>
                <label className="block text-sm font-medium text-gray-700">System Prompt</label>
                <textarea
                    rows="4"
                    className="w-full p-2 mt-1 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="e.g., You are a support ticket analyst. Analyze the provided data and extract key information."
                    value={step.prompt || ''}
                    onChange={e => updateStep({ ...step, prompt: e.target.value })}
                />
                {generatedJsonPrompt && <p className="text-xs text-gray-500 mt-1 italic">{generatedJsonPrompt}</p>}
            </div>
            <div className="space-y-2">
                <h4 className="text-sm font-medium text-gray-700">Data to Analyze</h4>
                <div className="p-2 bg-gray-50 rounded-md border space-y-2">
                    {aiInputs.map(input => (
                        <div key={input} className="flex items-center justify-between bg-white p-1 rounded">
                            <span className="text-sm font-medium text-indigo-700">{trigger.object}: {MOCK_DATA.schemas[trigger.object]?.properties.find(p=>p.name === input)?.label}</span>
                            <button onClick={() => handleRemoveInput(input)} className="text-gray-400 hover:text-red-500"><XCircleIcon/></button>
                        </div>
                    ))}
                    {triggerSchema && (
                        <select onChange={e => handleAddInput(e.target.value)} value="" className="p-1.5 border border-gray-300 rounded-md bg-white w-full text-sm">
                            <option value="" disabled>+ Map data from trigger...</option>
                            {triggerSchema.properties.filter(prop => !aiInputs.includes(prop.name)).map(prop => <option key={prop.name} value={prop.name}>{prop.label}</option>)}
                        </select>
                    )}
                </div>
            </div>
            <div className="space-y-2">
                <h4 className="text-sm font-medium text-gray-700">Define AI Response Structure</h4>
                {responseStructure.map(field => (
                    <div key={field.id} className="flex items-center space-x-2 bg-gray-50 p-2 rounded-md">
                        <input
                            type="text"
                            placeholder="Field Name"
                            value={field.name}
                            onChange={e => handleUpdateField(field.id, 'name', e.target.value)}
                            className="p-1.5 border border-gray-300 rounded-md w-full"
                        />
                        <select
                            value={field.type}
                            onChange={e => handleUpdateField(field.id, 'type', e.target.value)}
                            className="p-1.5 border border-gray-300 rounded-md bg-white"
                        >
                            {Object.keys(MOCK_DATA.dataTypes).filter(t => t !== 'Date' && t !== 'User' && t !== 'Select').map(type => <option key={type} value={type}>{type}</option>)}
                        </select>
                        <button onClick={() => handleDeleteField(field.id)} className="text-gray-400 hover:text-red-500 p-1"><TrashIcon /></button>
                    </div>
                ))}
                <Button onClick={handleAddField} variant="secondary" className="w-full text-xs py-1.5">+ Add Field</Button>
            </div>
        </StepCard>
    );
};

const ActionStep = ({ step, updateStep, onDelete, stepsBefore = [] }) => {
    const handleCategoryChange = (category) => updateStep({ ...step, category, type: null, config: {} });
    const handleTypeChange = (type) => updateStep({ ...step, type, config: {} });
    const handleConfigChange = (key, value) => updateStep({ ...step, config: { ...step.config, [key]: value }});

    // A more powerful data inserter
    const DataTokenInserter = ({ onInsert }) => {
        const sources = useMemo(() => {
            const trigger = stepsBefore.find(s => s.type === 'trigger');
            const sourceList = [];
            if(trigger && trigger.object) {
                sourceList.push({ name: `Trigger: ${trigger.object}`, fields: MOCK_DATA.schemas[trigger.object].properties.map(p => ({ label: p.label, value: `{{trigger.${p.name}}}` })) });
            }
            stepsBefore.forEach((s, i) => {
                if(s.type === 'ai' && s.responseStructure) {
                    sourceList.push({ name: `Step ${i+1}: AI Response`, fields: s.responseStructure.map(f => ({ label: f.name, value: `{{step_${s.id}.${f.name}}}` })) });
                }
            });
            return sourceList;
        }, [stepsBefore]);

        return (
            <select
                onChange={e => {
                    onInsert(e.target.value);
                    e.target.value = ""; // Reset dropdown after selection
                }}
                value=""
                className="p-1 border border-gray-300 rounded-md bg-white text-xs"
            >
                <option value="" disabled>+ Insert Data</option>
                {sources.map(source => (
                    <optgroup key={source.name} label={source.name}>
                        {source.fields.map(field => <option key={field.value} value={field.value}>{field.label}</option>)}
                    </optgroup>
                ))}
            </select>
        );
    };

    const renderActionConfig = () => {
        if (step.type === 'Create Draft Email') {
            return (
                <div className="space-y-2 mt-4 border-t pt-4">
                    <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-gray-700">Subject</label>
                        <DataTokenInserter onInsert={token => handleConfigChange('subject', `${step.config?.subject || ''}${token}`)} />
                    </div>
                    <input type="text" value={step.config?.subject || ''} onChange={e => handleConfigChange('subject', e.target.value)} className="w-full p-2 border border-gray-300 rounded-md" />

                    <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-gray-700">Body</label>
                        <DataTokenInserter onInsert={token => handleConfigChange('body', `${step.config?.body || ''}${token}`)} />
                    </div>
                    <textarea rows="5" value={step.config?.body || ''} onChange={e => handleConfigChange('body', e.target.value)} className="w-full p-2 border border-gray-300 rounded-md" />

                    <label className="text-sm font-medium text-gray-700">Set Status</label>
                    <input type="text" placeholder="e.g., approval_required or draft" value={step.config?.status || ''} onChange={e => handleConfigChange('status', e.target.value)} className="w-full p-2 border border-gray-300 rounded-md" />
                </div>
            );
        }
        if (step.type === 'Create a sub-task') {
            return (
                <div className="space-y-2 mt-4 border-t pt-4">
                    <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-gray-700">Task Name</label>
                        <DataTokenInserter onInsert={token => handleConfigChange('name', `${step.config?.name || ''}${token}`)} />
                    </div>
                    <input type="text" placeholder="e.g., Follow up on {{trigger.subject}}" value={step.config?.name || ''} onChange={e => handleConfigChange('name', e.target.value)} className="w-full p-2 border border-gray-300 rounded-md" />

                    <label className="text-sm font-medium text-gray-700">Assign to</label>
                    {/* A proper user selector would go here. For now, a text input. */}
                    <input type="text" placeholder="User or Role" value={step.config?.assignee || ''} onChange={e => handleConfigChange('assignee', e.target.value)} className="w-full p-2 border border-gray-300 rounded-md" />
                </div>
            );
        }
        return null;
    }


    return (
        <StepCard icon={MOCK_DATA.actions[step.category]?.icon || '⚙️'} title="Perform an Action" onDelete={onDelete}>
            <div className="flex items-center space-x-2">
                <select value={step.category || ''} onChange={e => handleCategoryChange(e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                    <option value="" disabled>Select category...</option>
                    {Object.keys(MOCK_DATA.actions).map(cat => <option key={cat} value={cat}>{cat}</option>)}
                </select>
                {step.category && (
                    <select value={step.type || ''} onChange={e => handleTypeChange(e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                        <option value="" disabled>Select action...</option>
                        {MOCK_DATA.actions[step.category].types.map(type => <option key={type} value={type}>{type}</option>)}
                    </select>
                )}
            </div>
            {step.type === 'Wait for Manual Approval' && (
                <div className="p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 text-sm mt-2 rounded-r-md">
                    <p>This workflow will pause at this step until a user manually approves it.</p>
                </div>
            )}
            {step.type === 'Check for Completion & Update' && (
                <div className="p-3 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm mt-2 rounded-r-md">
                    <p className="font-semibold">This is a smart action.</p>
                    <p>It will automatically check if all other tasks in the milestone are complete. If they are, it will mark the milestone as completed and set its completion date to today.</p>
                </div>
            )}
            {renderActionConfig()}
        </StepCard>
    );
};

const ConditionStep = ({ step, updateStep, onDelete, stepsBefore = [] }) => {
    const { conditionConfig = {} } = step;

    // Fixed bug by making dependency more robust
    const robustStepsDep = JSON.stringify(stepsBefore.map(s => ({id: s.id, type: s.type, object: s.object, responseStructure: s.responseStructure})));

    const availableDataSources = useMemo(() => {
        const sources = [];
        const trigger = stepsBefore.find(s => s.type === 'trigger');
        if (trigger && trigger.object) {
            sources.push({
                id: 'trigger',
                name: `Trigger: ${trigger.object}`,
                schema: MOCK_DATA.schemas[trigger.object],
            });
        }
        stepsBefore.forEach((s, i) => {
            if (s.type === 'ai' && s.responseStructure && s.responseStructure.length > 0) {
                sources.push({
                    id: s.id,
                    name: `Step ${i + 1}: AI Response`,
                    schema: {
                        properties: s.responseStructure.map(f => ({ name: f.name, label: f.name, type: f.type }))
                    }
                });
            }
        });
        return sources;
    }, [robustStepsDep]);

    const handleConfigChange = (key, value) => {
        const newConfig = { ...conditionConfig, [key]: value };
        if (key === 'sourceId') {
            delete newConfig.field;
            delete newConfig.operator;
            delete newConfig.value;
        }
        if (key === 'field') {
            delete newConfig.operator;
            delete newConfig.value;
        }
        updateStep({ ...step, conditionConfig: newConfig });
    };

    const selectedSource = availableDataSources.find(s => s.id == conditionConfig.sourceId); // Using == to handle string/number type mismatch
    const selectedProperty = selectedSource?.schema.properties.find(p => p.name === conditionConfig.field);
    const operators = selectedProperty ? MOCK_DATA.dataTypes[selectedProperty.type]?.operators : [];

    return (
        <StepCard icon="🔀" title="If/Else Condition" onDelete={onDelete}>
            <div className="flex flex-col space-y-2 text-md p-2 bg-gray-50 rounded-md">
                <span className="font-semibold text-gray-700">If...</span>
                <div className="grid grid-cols-2 gap-2">
                    <select value={conditionConfig.sourceId || ''} onChange={e => handleConfigChange('sourceId', e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm col-span-2">
                        <option value="" disabled>Select data source...</option>
                        {availableDataSources.map(source => <option key={source.id} value={source.id}>{source.name}</option>)}
                    </select>

                    {selectedSource && (
                        <select value={conditionConfig.field || ''} onChange={e => handleConfigChange('field', e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm col-span-2">
                            <option value="" disabled>Select field...</option>
                            {selectedSource.schema.properties.map(prop => <option key={prop.name} value={prop.name}>{prop.label}</option>)}
                        </select>
                    )}

                    {selectedProperty && (
                        <>
                            <select value={conditionConfig.operator || ''} onChange={e => handleConfigChange('operator', e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                                <option value="" disabled>is...</option>
                                {operators.map(op => <option key={op} value={op}>{op}</option>)}
                            </select>

                            {selectedProperty.type === 'True/False' ? null :
                                selectedProperty.type === 'Select' ? (
                                    <select value={conditionConfig.value || ''} onChange={e => handleConfigChange('value', e.target.value)} className="p-2 border border-gray-300 rounded-md bg-white shadow-sm">
                                        <option value="" disabled>Select value...</option>
                                        {selectedProperty.options.map(opt => <option key={opt} value={opt}>{opt}</option>)}
                                    </select>
                                ) : (
                                    <input
                                        type={selectedProperty.type === 'Date' ? 'date' : selectedProperty.type === 'Number' ? 'number' : 'text'}
                                        value={conditionConfig.value || ''}
                                        onChange={e => handleConfigChange('value', e.target.value)}
                                        className="p-2 border border-gray-300 rounded-md bg-white shadow-sm"
                                    />
                                )}
                        </>
                    )}
                </div>
            </div>
        </StepCard>
    );
};


const Workflow = ({ steps, onUpdate, parentPath = '', fullContextSteps = [] }) => {
    const handleUpdateStep = (stepIndex, newStepData) => {
        const newSteps = [...steps];
        newSteps[stepIndex] = newStepData;
        onUpdate(newSteps);
    };

    const handleAddStep = (stepIndex, type) => {
        const newSteps = [...steps];
        let newStep = { id: Date.now(), type };
        if (type === 'condition') {
            newStep.if_true = [];
            newStep.if_false = [];
        }
        newSteps.splice(stepIndex, 0, newStep);
        onUpdate(newSteps);
    };

    const handleDeleteStep = (stepIndex) => {
        const newSteps = steps.filter((_, index) => index !== stepIndex);
        onUpdate(newSteps);
    };

    const renderStep = (step, index) => {
        const path = `${parentPath}${index}`;
        const localStepsBefore = steps.slice(0, index);
        const allStepsBefore = [...fullContextSteps, ...localStepsBefore];

        switch (step.type) {
            case 'trigger':
                return <TriggerStep key={path} step={step} updateStep={(data) => handleUpdateStep(index, data)} />;
            case 'ai':
                return <AIStep key={path} step={step} updateStep={(data) => handleUpdateStep(index, data)} onDelete={() => handleDeleteStep(index)} stepsBefore={allStepsBefore} />;
            case 'action':
                return <ActionStep key={path} step={step} updateStep={(data) => handleUpdateStep(index, data)} onDelete={() => handleDeleteStep(index)} stepsBefore={allStepsBefore} />;
            case 'condition':
                const newContextForChildren = [...fullContextSteps, ...steps.slice(0, index + 1)];
                return (
                    <div key={path} className="w-full flex flex-col items-center">
                        <ConditionStep step={step} updateStep={(data) => handleUpdateStep(index, data)} onDelete={() => handleDeleteStep(index)} stepsBefore={allStepsBefore} />
                        <div className="w-full flex mt-4 space-x-4">
                            <div className="flex-1 bg-green-50/50 p-4 rounded-lg border border-green-200">
                                <p className="text-center font-bold text-green-700 mb-4">IF YES</p>
                                <Workflow
                                    steps={step.if_true}
                                    onUpdate={(newBranchSteps) => handleUpdateStep(index, { ...step, if_true: newBranchSteps })}
                                    parentPath={`${path}-t`}
                                    fullContextSteps={newContextForChildren} />
                            </div>
                            <div className="flex-1 bg-red-50/50 p-4 rounded-lg border border-red-200">
                                <p className="text-center font-bold text-red-700 mb-4">IF NO</p>
                                <Workflow
                                    steps={step.if_false}
                                    onUpdate={(newBranchSteps) => handleUpdateStep(index, { ...step, if_false: newBranchSteps })}
                                    parentPath={`${path}-f`}
                                    fullContextSteps={newContextForChildren} />
                            </div>
                        </div>
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <div className="flex flex-col items-center w-full space-y-4">
            {steps.map(renderStep)}
            <AddStepButton onClick={(type) => handleAddStep(steps.length, type)} />
        </div>
    );
};

const AddStepButton = ({ onClick }) => {
    const [isOpen, setIsOpen] = useState(false);

    const handleSelect = (type) => {
        onClick(type);
        setIsOpen(false);
    };

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="inline-flex items-center gap-x-1.5 rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            >
                <PlusIcon size={16} />
                Add Step
            </button>
            {isOpen && (
                <div className="absolute z-10 mt-2 w-72 origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                    <div className="py-1">
                        {Object.entries(MOCK_DATA.stepTypes).map(([key, { name, icon, description }]) => (
                            <a
                                key={key}
                                href="#"
                                onClick={(e) => { e.preventDefault(); handleSelect(key); }}
                                className="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100"
                            >
                                <div className="flex items-start space-x-3">
                                    <span className="text-xl mt-1">{icon}</span>
                                    <div>
                                        <p className="font-semibold">{name}</p>
                                        <p className="text-xs text-gray-500">{description}</p>
                                    </div>
                                </div>
                            </a>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

const AutomationBuilder = ({ onBack }) => {
    const [workflow, setWorkflow] = useState([{ type: 'trigger', id: 'start' }]);
    const [name, setName] = useState('');

    const isReadyForSave = workflow[0]?.event && name;

    return (
        <div className="max-w-4xl mx-auto space-y-6">
            <div className="bg-white p-4 rounded-lg shadow-md border flex justify-between items-center sticky top-4 z-20">
                <input
                    type="text"
                    value={name}
                    onChange={e => setName(e.target.value)}
                    placeholder="Untitled Automation"
                    className="text-xl font-bold text-gray-800 focus:outline-none bg-transparent"
                />
                <div className="flex space-x-2">
                    <Button onClick={onBack} variant="secondary">Cancel</Button>
                    <Button onClick={onBack} disabled={!isReadyForSave}>Save and Activate</Button>
                </div>
            </div>
            <div className="p-6">
                <Workflow steps={workflow} onUpdate={setWorkflow} />
            </div>
        </div>
    );
};

// --- Main Hub and App components (mostly unchanged) ---
const AutomationHub = ({ onNew }) => {
    return (
        <div className="max-w-5xl mx-auto space-y-8">
            <div className="flex justify-between items-center">
                <h1 className="text-3xl font-bold text-gray-900">Automation Hub</h1>
                <Button onClick={onNew} className="flex items-center space-x-2">
                    <PlusIcon />
                    <span>Create New Automation</span>
                </Button>
            </div>

            <Card>
                <h2 className="text-xl font-bold text-gray-800 mb-4">My Automations</h2>
                <div className="space-y-3">
                    {MOCK_DATA.myAutomations.map((auto, index) => (
                        <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                            <div className="flex items-center space-x-4">
                                <div className={`w-10 h-10 rounded-full flex items-center justify-center ${auto.active ? 'bg-green-100' : 'bg-gray-200'}`}>
                                    <span className="text-xl">🤖</span>
                                </div>
                                <div>
                                    <p className="font-semibold text-gray-800">{auto.name}</p>
                                    <p className="text-xs text-gray-500 flex items-center space-x-1.5">
                                        {auto.status === 'ok' ? <CheckCircleIcon/> : <XCircleIcon/>}
                                        <span>Last ran: {auto.lastRan}</span>
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center space-x-4">
                                <label htmlFor={`toggle-${index}`} className="flex items-center cursor-pointer">
                                    <div className="relative">
                                        <input type="checkbox" id={`toggle-${index}`} className="sr-only" defaultChecked={auto.active} />
                                        <div className="block bg-gray-300 w-12 h-6 rounded-full"></div>
                                        <div className="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
                                    </div>
                                </label>
                                <button className="text-gray-400 hover:text-gray-600">...</button>
                            </div>
                        </div>
                    ))}
                </div>
            </Card>

            <Card>
                <h2 className="text-xl font-bold text-gray-800 mb-4">Start with a Template 💡</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {MOCK_DATA.templates.map(template => (
                        <div key={template.title} className="p-4 bg-white rounded-lg border border-gray-200 hover:shadow-lg hover:border-indigo-300 transition-all duration-200 cursor-pointer">
                            <div className="flex items-start space-x-4">
                                <div className="text-3xl mt-1">{template.icon}</div>
                                <div>
                                    <p className="font-semibold text-gray-900">{template.title}</p>
                                    <p className="text-sm text-gray-500">{template.description}</p>
                                </div>
                                <ArrowRightIcon/>
                            </div>
                        </div>
                    ))}
                </div>
            </Card>

            <style>{`
                input:checked ~ .dot {
                    transform: translateX(100%);
                    left: 0.5rem; /* adjusted for size */
                }
                input:checked ~ .block {
                    background-color: #4f46e5;
                }
            `}</style>
        </div>
    );
};


export default function App() {
    const [view, setView] = useState('hub'); // 'hub' or 'builder'

    useEffect(() => {
        document.body.className = 'bg-gray-100';
    }, []);

    const renderContent = () => {
        switch (view) {
            case 'builder':
                return <AutomationBuilder onBack={() => setView('hub')} />;
            case 'hub':
            default:
                return <AutomationHub onNew={() => setView('builder')} />;
        }
    };

    return (
        <div className="p-8 font-sans">
            {renderContent()}
        </div>
    );
}

