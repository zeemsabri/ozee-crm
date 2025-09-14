import React, { useState, useMemo, useEffect } from 'react';

// --- MOCK DATA based on the 'prompts' table schema ---
const MOCK_DATA = {
    prompts: [
        {
            id: 1,
            name: 'Email Outreach for Law Firms',
            category: 'Sales',
            version: 2,
            system_prompt_text: "You are an AI assistant for a web design agency. Your goal is to write a personalized email to a law firm. Use the provided {{company_website}} and {{first_name}} to craft a compelling message...",
            model_name: 'gemini-2.5-flash-preview-05-20',
            generation_config: { temperature: 0.7, topP: 1.0, maxOutputTokens: 1024 },
            template_variables: ['company_website', 'first_name', 'company_name'],
            status: 'active',
            updated_at: '2025-09-12T04:30:00Z'
        },
        {
            id: 2,
            name: 'Email Outreach for Law Firms',
            category: 'Sales',
            version: 1,
            system_prompt_text: "You are an AI assistant. Write an email to {{company_name}}.",
            model_name: 'gemini-2.5-flash-preview-05-20',
            generation_config: { temperature: 0.8 },
            template_variables: ['company_name'],
            status: 'archived',
            updated_at: '2025-08-20T11:00:00Z'
        },
        {
            id: 3,
            name: 'Customer Support Ticket Triage',
            category: 'Support',
            version: 1,
            system_prompt_text: "Analyze the following support ticket: {{ticket_body}}. Respond with JSON indicating the 'urgency' (high, medium, low) and 'category' (billing, technical, general).",
            model_name: 'gemini-2.5-flash-preview-05-20',
            generation_config: { responseMimeType: 'application/json' },
            template_variables: ['ticket_body'],
            status: 'active',
            updated_at: '2025-09-10T08:00:00Z'
        },
        {
            id: 4,
            name: 'New Feature Brainstormer',
            category: 'Product',
            version: 1,
            system_prompt_text: "You are a product manager. Based on the feature request: {{feature_request}}, brainstorm three potential user stories.",
            model_name: 'gemini-2.5-flash-preview-05-20',
            generation_config: { temperature: 0.9 },
            template_variables: ['feature_request'],
            status: 'draft',
            updated_at: '2025-09-13T01:15:00Z'
        },
    ],
    promptCategories: ['Sales', 'Support', 'Product', 'Marketing', 'General']
};

// --- SVG ICONS ---
const PlusIcon = ({size = 20}) => <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>;
const ChevronDownIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>;
const FilePlusIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>;
const XCircleIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 hover:text-red-500"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>;
const CodeIcon = () => <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>;


// --- HELPER COMPONENTS ---
const Card = ({ children, className }) => <div className={`bg-white rounded-lg shadow-sm border border-gray-200 ${className}`}>{children}</div>;
const Button = ({ children, onClick, variant = 'primary', className = '', disabled = false }) => {
    const baseClasses = 'px-4 py-2 rounded-md font-semibold text-sm transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed flex items-center justify-center gap-2';
    const variantClasses = {
        primary: 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 disabled:bg-indigo-300',
        secondary: 'bg-gray-200 text-gray-800 hover:bg-gray-300 focus:ring-gray-400 disabled:bg-gray-100',
        ghost: 'bg-transparent text-gray-600 hover:bg-gray-100'
    };
    return <button onClick={onClick} disabled={disabled} className={`${baseClasses} ${variantClasses[variant]} ${className}`}>{children}</button>;
};
const StatusBadge = ({ status }) => {
    const styles = {
        active: 'bg-green-100 text-green-800',
        draft: 'bg-yellow-100 text-yellow-800',
        archived: 'bg-gray-100 text-gray-600'
    };
    return <span className={`px-2 py-1 text-xs font-medium rounded-full ${styles[status]}`}>{status}</span>;
}

// --- MAIN COMPONENTS ---

const PromptEditor = ({ prompt, onSave, onCancel }) => {
    const [editedPrompt, setEditedPrompt] = useState(prompt);
    const [newVariable, setNewVariable] = useState('');
    const [showJsonEditor, setShowJsonEditor] = useState(false);

    const handleVariableAdd = () => {
        if (newVariable && !editedPrompt.template_variables.includes(newVariable)) {
            const updatedVariables = [...editedPrompt.template_variables, newVariable.trim()];
            setEditedPrompt({ ...editedPrompt, template_variables: updatedVariables });
        }
        setNewVariable('');
    };

    const handleVariableRemove = (variableToRemove) => {
        const updatedVariables = editedPrompt.template_variables.filter(v => v !== variableToRemove);
        setEditedPrompt({ ...editedPrompt, template_variables: updatedVariables });
    }

    const handleChange = (field, value) => {
        setEditedPrompt({ ...editedPrompt, [field]: value });
    };

    const handleGenConfigChange = (field, value) => {
        const newConfig = { ...editedPrompt.generation_config, [field]: value };
        // Clean up empty values
        if (value === '' || value === null) {
            delete newConfig[field];
        }
        setEditedPrompt({ ...editedPrompt, generation_config: newConfig });
    }

    const isNew = !prompt.id;

    return (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-7xl mx-auto">
            <div className="md:col-span-2">
                <Card className="h-full">
                    <div className="p-4 space-y-4">
                        <h2 className="text-xl font-bold text-gray-800">{isNew ? 'Create New Prompt' : `Editing: ${prompt.name}`}</h2>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">System Prompt Text</label>
                            <textarea
                                rows="15"
                                className="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                placeholder="You are a helpful AI assistant..."
                                value={editedPrompt.system_prompt_text}
                                onChange={e => handleChange('system_prompt_text', e.target.value)}
                            />
                        </div>
                    </div>
                </Card>
            </div>
            <div className="space-y-6">
                <Card>
                    <div className="p-4 space-y-4">
                        <div className="flex justify-end space-x-2">
                            <Button onClick={onCancel} variant="secondary">Cancel</Button>
                            <Button onClick={() => onSave(editedPrompt)}>{isNew ? 'Create Prompt' : 'Save Changes'}</Button>
                        </div>
                        {!isNew && <Button onClick={() => onSave({ ...editedPrompt, version: editedPrompt.version + 1 })} variant="secondary" className="w-full"><FilePlusIcon /> Save as New Version (v{editedPrompt.version + 1})</Button>}
                    </div>
                </Card>
                <Card>
                    <div className="p-4 space-y-4">
                        <h3 className="font-bold text-gray-700">Configuration</h3>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Prompt Name</label>
                            <input type="text" value={editedPrompt.name} onChange={e => handleChange('name', e.target.value)} className="w-full p-2 mt-1 border border-gray-300 rounded-md" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Category</label>
                            <select value={editedPrompt.category} onChange={e => handleChange('category', e.target.value)} className="w-full p-2 mt-1 border border-gray-300 rounded-md bg-white">
                                {MOCK_DATA.promptCategories.map(cat => (
                                    <option key={cat} value={cat}>{cat}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Status</label>
                            <select value={editedPrompt.status} onChange={e => handleChange('status', e.target.value)} className="w-full p-2 mt-1 border border-gray-300 rounded-md bg-white">
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Version</label>
                            <p className="p-2 mt-1 text-gray-500">{editedPrompt.version}</p>
                        </div>
                    </div>
                </Card>
                <Card>
                    <div className="p-4 space-y-4">
                        <h3 className="font-bold text-gray-700">Template Variables</h3>
                        <div className="flex flex-wrap gap-2">
                            {editedPrompt.template_variables.map(v => (
                                <div key={v} className="flex items-center bg-indigo-100 text-indigo-800 text-sm font-medium pl-2 pr-1 py-1 rounded-full">
                                    {`{{${v}}}`}
                                    <button onClick={() => handleVariableRemove(v)} className="ml-1"><XCircleIcon /></button>
                                </div>
                            ))}
                        </div>
                        <div className="flex space-x-2">
                            <input
                                type="text"
                                value={newVariable}
                                onChange={e => setNewVariable(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && handleVariableAdd()}
                                placeholder="Add variable name..."
                                className="w-full p-2 border border-gray-300 rounded-md"
                            />
                            <Button onClick={handleVariableAdd}>Add</Button>
                        </div>
                    </div>
                </Card>
                <Card>
                    <details className="p-4 group" open>
                        <summary className="font-bold text-gray-700 list-none flex justify-between items-center cursor-pointer">
                            Advanced Settings
                            <ChevronDownIcon className="group-open:rotate-180 transition-transform"/>
                        </summary>
                        <div className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Model Name</label>
                                <input type="text" value={editedPrompt.model_name} onChange={e => handleChange('model_name', e.target.value)} className="w-full p-2 mt-1 border border-gray-300 rounded-md font-mono text-sm" />
                            </div>

                            <div className="border-t pt-4">
                                <div className="flex justify-between items-center mb-2">
                                    <h4 className="font-medium text-gray-700">Generation Config</h4>
                                    <Button onClick={() => setShowJsonEditor(!showJsonEditor)} variant="ghost" className="text-xs py-1 px-2">
                                        <CodeIcon />
                                        {showJsonEditor ? 'Use Form' : 'Use JSON Editor'}
                                    </Button>
                                </div>

                                {showJsonEditor ? (
                                    <textarea
                                        rows="6"
                                        className="w-full p-2 mt-1 border border-gray-300 rounded-md font-mono text-sm"
                                        value={JSON.stringify(editedPrompt.generation_config, null, 2)}
                                        onChange={e => {
                                            try {
                                                handleChange('generation_config', JSON.parse(e.target.value))
                                            } catch (err) {
                                                // Handle invalid JSON gracefully
                                            }
                                        }}
                                    />
                                ) : (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Temperature</label>
                                            <input
                                                type="range"
                                                min="0" max="1" step="0.1"
                                                value={editedPrompt.generation_config?.temperature || 0.7}
                                                onChange={e => handleGenConfigChange('temperature', parseFloat(e.target.value))}
                                                className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span className="text-xs text-gray-500 text-center block">{editedPrompt.generation_config?.temperature || 0.7} (Creativity)</span>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Max Output Tokens</label>
                                            <input
                                                type="number"
                                                placeholder="e.g., 1024"
                                                value={editedPrompt.generation_config?.maxOutputTokens || ''}
                                                onChange={e => handleGenConfigChange('maxOutputTokens', parseInt(e.target.value))}
                                                className="w-full p-2 mt-1 border border-gray-300 rounded-md"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Response Format</label>
                                            <select
                                                value={editedPrompt.generation_config?.responseMimeType || 'text/plain'}
                                                onChange={e => handleGenConfigChange('responseMimeType', e.target.value)}
                                                className="w-full p-2 mt-1 border border-gray-300 rounded-md bg-white"
                                            >
                                                <option value="text/plain">Text</option>
                                                <option value="application/json">JSON</option>
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </details>
                </Card>
            </div>
        </div>
    );
};

const PromptLibrary = ({ onEdit, onCreate }) => {
    const [prompts, setPrompts] = useState(MOCK_DATA.prompts);

    const groupedPrompts = useMemo(() => {
        const groups = {};
        prompts.forEach(p => {
            if (!groups[p.name] || groups[p.name].version < p.version) {
                groups[p.name] = p;
            }
        });
        return Object.values(groups);
    }, [prompts]);

    const timeSince = (date) => {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    };

    return (
        <div className="max-w-7xl mx-auto space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-3xl font-bold text-gray-900">Prompt Library</h1>
                <Button onClick={onCreate}>
                    <PlusIcon />
                    Create New Prompt
                </Button>
            </div>
            <Card className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                    <tr>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Latest Version</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th scope="col" className="relative px-6 py-3"><span className="sr-only">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                    {groupedPrompts.map(p => (
                        <tr key={p.id}>
                            <td className="px-6 py-4 whitespace-nowrap">
                                <div className="text-sm font-medium text-gray-900">{p.name}</div>
                            </td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{p.category}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">v{p.version}</td>
                            <td className="px-6 py-4 whitespace-nowrap"><StatusBadge status={p.status} /></td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{timeSince(p.updated_at)}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <button onClick={() => {}} className="text-gray-500 hover:text-gray-800">View Versions</button>
                                <button onClick={() => onEdit(p)} className="text-indigo-600 hover:text-indigo-900">Edit</button>
                            </td>
                        </tr>
                    ))}
                    </tbody>
                </table>
            </Card>
        </div>
    );
};

export default function App() {
    const [view, setView] = useState('library'); // 'library' or 'editor'
    const [editingPrompt, setEditingPrompt] = useState(null);

    useEffect(() => {
        document.body.className = 'bg-gray-50';
    }, []);

    const handleCreate = () => {
        setEditingPrompt({
            name: 'New Prompt',
            category: 'General',
            version: 1,
            system_prompt_text: "You are a helpful AI assistant.\n\nYour goal is to process the input provided in the template variables and respond as instructed.\n\nThis is an example of a template variable: {{example_variable}}. You can add more variables in the 'Template Variables' section on the right.",
            model_name: 'gemini-2.5-flash-preview-05-20',
            generation_config: { temperature: 0.7, maxOutputTokens: 2048, responseMimeType: 'text/plain'},
            template_variables: ['example_variable'],
            status: 'draft'
        });
        setView('editor');
    };

    const handleEdit = (prompt) => {
        setEditingPrompt(prompt);
        setView('editor');
    };

    const handleSave = (prompt) => {
        // In a real app, you would send this to your backend API
        console.log('Saving prompt:', prompt);
        setView('library');
        setEditingPrompt(null);
    };

    const handleCancel = () => {
        setView('library');
        setEditingPrompt(null);
    };

    const renderContent = () => {
        switch (view) {
            case 'editor':
                return <PromptEditor prompt={editingPrompt} onSave={handleSave} onCancel={handleCancel} />;
            case 'library':
            default:
                return <PromptLibrary onEdit={handleEdit} onCreate={handleCreate} />;
        }
    };

    return <div className="p-8 font-sans">{renderContent()}</div>;
}

