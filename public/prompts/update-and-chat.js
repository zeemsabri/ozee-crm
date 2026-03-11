import React, { useState } from 'react';
import {
    Bell,
    MessageSquare,
    X,
    Check,
    Clock,
    User,
    Calendar,
    CheckCircle2,
    Filter,
    Send,
    MoreVertical,
    CheckCheck,
    ChevronDown,
    Maximize,
    Minimize,
    CornerDownRight,
    Mail,
    ExternalLink
} from 'lucide-react';

// Mock Data
const initialNotifications = [
    {
        id: 1,
        type: 'meeting',
        title: 'Q3 Planning Strategy',
        message: 'Sarah Jenks invited you to a meeting.',
        time: '10m ago',
        unread: true,
        actionable: true
    },
    {
        id: 2,
        type: 'mention',
        title: 'Project: Website Redesign',
        message: 'Mike Roberts mentioned you: "@you Can you review the latest Figma drafts when you have a second?"',
        time: '1h ago',
        unread: true,
        actionable: false
    },
    {
        id: 3,
        type: 'group',
        title: 'Task Updates: Marketing V2',
        message: 'John Doe and 3 others completed 3 tasks.',
        time: '2h ago',
        unread: false,
        actionable: false,
        subTasks: [
            { id: 101, title: 'Update homepage hero image' },
            { id: 102, title: 'Draft new email newsletter' },
            { id: 103, title: 'Review Q2 analytics report' }
        ]
    },
    {
        id: 5,
        type: 'group',
        title: 'New Tasks Assigned: Website Redesign',
        message: 'David Smith assigned 4 new tasks to you.',
        time: '3h ago',
        unread: true,
        actionable: false,
        subTasks: [
            { id: 104, title: 'Design mobile navigation menu' },
            { id: 105, title: 'Optimize hero background images' },
            { id: 106, title: 'Fix footer alignment on tablet' },
            { id: 107, title: 'Update team avatars on about page' }
        ]
    },
    {
        id: 6,
        type: 'group',
        title: 'Document Approvals: Q3 Reports',
        message: '2 documents require your final sign-off.',
        time: '5h ago',
        unread: false,
        actionable: false,
        subTasks: [
            { id: 108, title: 'Q3 Financial Summary.pdf' },
            { id: 109, title: 'Marketing Spend Analysis_v2.xlsx' }
        ]
    },
    {
        id: 4,
        type: 'system',
        title: 'Invoice Paid',
        message: 'Client Acme Corp has paid Invoice #1042.',
        time: 'Yesterday',
        unread: false,
        actionable: false
    }
];

const initialChat = [
    { id: 1, user: 'Sarah Jenks', initials: 'SJ', color: 'bg-emerald-500', message: 'Has anyone seen the updated brief for the Acme Corp project?', time: '10:42 AM', type: 'text' },
    { id: 2, user: 'Mike Roberts', initials: 'MR', color: 'bg-blue-500', message: 'I think David moved it to the archive folder by mistake. I will pull it back out.', time: '10:44 AM', type: 'text' },
    { id: 4, user: 'Acme Corp (Client)', initials: 'AC', color: 'bg-slate-700', summary: 'Client inquired about the expected submission date for the wireframes.', time: '11:15 AM', type: 'email', direction: 'inbound' },
    { id: 5, user: 'Sarah Jenks', initials: 'SJ', color: 'bg-emerald-500', summary: 'User is providing an update on 19 remaining tasks, mostly image-related. User suggests a short screen-sharing session to review and resolve image-related issues efficiently, or to proceed with completing tasks and moving to the next feedback phase.', time: '11:30 AM', type: 'email', direction: 'outbound' },
    { id: 3, user: 'You', initials: 'ME', color: 'bg-indigo-600', message: 'Thanks Mike! Let me know when it is back so I can attach it to the new task.', time: '11:45 AM', type: 'text' }
];

const availableContexts = [
    { id: 'p1', type: 'Project', name: 'Website Redesign', color: 'bg-emerald-500', unreadCount: 0 },
    { id: 'p2', type: 'Project', name: 'Marketing V2', color: 'bg-purple-500', unreadCount: 3 },
    { id: 't1', type: 'Team', name: 'Design Team', color: 'bg-blue-500', unreadCount: 1 },
];

export default function App() {
    const [isOpen, setIsOpen] = useState(true);
    const [isFullScreen, setIsFullScreen] = useState(false);
    const [activeTab, setActiveTab] = useState('notifications'); // 'notifications' or 'chat'
    const [filter, setFilter] = useState('all'); // 'all', 'unread', 'mentions'

    // Chat Context State
    const [activeContext, setActiveContext] = useState(availableContexts[0]);
    const [showContextDropdown, setShowContextDropdown] = useState(false);

    // Notification states
    const [notifications, setNotifications] = useState(initialNotifications);
    const [expandedGroups, setExpandedGroups] = useState({});
    const [replyingTo, setReplyingTo] = useState(null);
    const [replyText, setReplyText] = useState('');

    // Chat states
    const [chatMessages, setChatMessages] = useState(initialChat);
    const [newMessage, setNewMessage] = useState('');

    const unreadCount = notifications.filter(n => n.unread).length;

    // Calculate unread chat messages across all contexts
    const unreadChatCount = availableContexts.reduce((sum, ctx) => sum + (ctx.unreadCount || 0), 0);
    const hasOtherUnreadChats = availableContexts.some(c => c.id !== activeContext.id && (c.unreadCount || 0) > 0);

    const markAsRead = (id) => {
        setNotifications(notifications.map(n => n.id === id ? { ...n, unread: false } : n));
    };

    const markAllAsRead = () => {
        setNotifications(notifications.map(n => ({ ...n, unread: false })));
    };

    const handleSendMessage = (e) => {
        e.preventDefault();
        if (!newMessage.trim()) return;

        const newMsg = {
            id: Date.now(),
            user: 'You',
            initials: 'ME',
            color: 'bg-indigo-600',
            message: newMessage,
            time: 'Just now',
            type: 'text'
        };

        setChatMessages([...chatMessages, newMsg]);
        setNewMessage('');
    };

    const toggleGroup = (id, e) => {
        e.stopPropagation(); // Prevent marking as read when expanding
        setExpandedGroups(prev => ({ ...prev, [id]: !prev[id] }));
    };

    const handleReplySubmit = (id, e) => {
        e.preventDefault();
        e.stopPropagation();
        // In a real app, this dispatches the reply to the backend via Reverb/API
        setReplyingTo(null);
        setReplyText('');
        markAsRead(id);
    };

    const filteredNotifications = notifications.filter(n => {
        if (filter === 'unread') return n.unread;
        if (filter === 'mentions') return n.type === 'mention';
        return true;
    });

    const getIconForType = (type) => {
        switch(type) {
            case 'meeting': return <Calendar className="w-5 h-5 text-purple-500" />;
            case 'mention': return <User className="w-5 h-5 text-blue-500" />;
            case 'group': return <CheckCircle2 className="w-5 h-5 text-emerald-500" />;
            default: return <Bell className="w-5 h-5 text-gray-400" />;
        }
    };

    const showNotifications = isFullScreen || activeTab === 'notifications';
    const showChat = isFullScreen || activeTab === 'chat';

    return (
        <div className="min-h-screen bg-slate-100 font-sans flex overflow-hidden">

            {/* Fake CRM Main Content Area */}
            <div className="flex-1 flex flex-col">
                <header className="bg-white border-b border-slate-200 h-16 flex items-center justify-between px-6">
                    <div className="text-xl font-bold text-slate-800 tracking-tight">Acme CRM</div>
                    <button
                        onClick={() => setIsOpen(!isOpen)}
                        className="relative p-2 text-slate-500 hover:bg-slate-100 rounded-full transition-colors"
                    >
                        <Bell className="w-6 h-6" />
                        {unreadCount > 0 && (
                            <span className="absolute top-1 right-1.5 flex h-3 w-3">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span>
              </span>
                        )}
                    </button>
                </header>
                <main className="p-8 flex-1 overflow-y-auto">
                    <div className="max-w-4xl mx-auto">
                        <h1 className="text-2xl font-bold text-slate-800 mb-6">Project Dashboard</h1>
                        <div className="grid grid-cols-3 gap-6">
                            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-48 flex items-center justify-center text-slate-400">Project Widget</div>
                            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-48 flex items-center justify-center text-slate-400">Task Widget</div>
                            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 h-48 flex items-center justify-center text-slate-400">Activity Widget</div>
                        </div>
                    </div>
                </main>
            </div>

            {/* Sidebar Overlay (Mobile only or when NOT full screen) */}
            {isOpen && !isFullScreen && (
                <div
                    className="fixed inset-0 bg-slate-900/20 backdrop-blur-sm z-40 lg:hidden"
                    onClick={() => setIsOpen(false)}
                />
            )}

            {/* The Sidebar / Full-Screen Pane */}
            <div className={`
        fixed bg-white shadow-2xl z-50 flex flex-col transform transition-all duration-300 ease-in-out
        ${isOpen ? 'translate-x-0' : 'translate-x-full'}
        ${isFullScreen ? 'inset-0 w-full' : 'inset-y-0 right-0 w-full sm:w-96 border-l border-slate-200'}
      `}>

                {/* Sidebar Header */}
                <div className="px-5 pt-5 pb-0 border-b border-slate-200 bg-white">
                    <div className="flex justify-between items-center mb-4">
                        <h2 className="text-lg font-semibold text-slate-800">
                            {isFullScreen ? 'Communication Center' : 'Inbox'}
                        </h2>
                        <div className="flex space-x-2">
                            <button
                                onClick={markAllAsRead}
                                className="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                title="Mark all as read"
                            >
                                <CheckCheck className="w-5 h-5" />
                            </button>
                            <button
                                onClick={() => setIsFullScreen(!isFullScreen)}
                                className="p-1.5 text-slate-400 hover:bg-slate-100 rounded-md transition-colors hidden sm:block"
                                title={isFullScreen ? "Minimize Sidebar" : "Maximize Full Screen"}
                            >
                                {isFullScreen ? <Minimize className="w-5 h-5" /> : <Maximize className="w-5 h-5" />}
                            </button>
                            <button
                                onClick={() => { setIsOpen(false); setIsFullScreen(false); }}
                                className="p-1.5 text-slate-400 hover:bg-slate-100 rounded-md transition-colors"
                                title="Close"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    {/* Tabs (Hidden in Full Screen Mode since both panels show) */}
                    {!isFullScreen && (
                        <div className="flex space-x-6">
                            <button
                                onClick={() => setActiveTab('notifications')}
                                className={`pb-3 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors ${activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                            >
                                <Bell className="w-4 h-4" />
                                <span>Updates</span>
                                {unreadCount > 0 && (
                                    <span className="bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs">{unreadCount}</span>
                                )}
                            </button>
                            <button
                                onClick={() => setActiveTab('chat')}
                                className={`pb-3 text-sm font-medium flex items-center space-x-2 border-b-2 transition-colors ${activeTab === 'chat' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}`}
                            >
                                <MessageSquare className="w-4 h-4" />
                                <span>Team Chat</span>
                                {unreadChatCount > 0 && (
                                    <span className="bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs">{unreadChatCount}</span>
                                )}
                            </button>
                        </div>
                    )}
                </div>

                {/* --- Content Area --- */}
                <div className="flex-1 flex overflow-hidden">

                    {/* --- NOTIFICATIONS PANE --- */}
                    {showNotifications && (
                        <div className={`flex flex-col bg-white overflow-hidden ${isFullScreen ? 'w-1/3 border-r border-slate-200' : 'w-full'}`}>

                            {/* Context Header for Full Screen Mode */}
                            {isFullScreen && (
                                <div className="px-5 py-3 border-b border-slate-100 bg-slate-50">
                                    <h3 className="text-sm font-semibold text-slate-700 flex items-center">
                                        <Bell className="w-4 h-4 mr-2 text-slate-500" />
                                        Updates & Alerts
                                    </h3>
                                </div>
                            )}

                            {/* Filters */}
                            <div className="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <div className="flex space-x-2">
                                    <button
                                        onClick={() => setFilter('all')}
                                        className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-200 text-slate-600 hover:bg-slate-300'}`}
                                    >
                                        All
                                    </button>
                                    <button
                                        onClick={() => setFilter('unread')}
                                        className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'}`}
                                    >
                                        Unread
                                    </button>
                                    <button
                                        onClick={() => setFilter('mentions')}
                                        className={`px-3 py-1 rounded-full text-xs font-medium transition-colors ${filter === 'mentions' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'}`}
                                    >
                                        @ Mentions
                                    </button>
                                </div>
                                <Filter className="w-4 h-4 text-slate-400 cursor-pointer hover:text-slate-600" />
                            </div>

                            {/* Notification List */}
                            <div className="flex-1 overflow-y-auto">
                                {filteredNotifications.length === 0 ? (
                                    <div className="flex flex-col items-center justify-center h-full text-slate-400 space-y-3">
                                        <Bell className="w-10 h-10 opacity-20" />
                                        <p className="text-sm">You're all caught up!</p>
                                    </div>
                                ) : (
                                    <div className="divide-y divide-slate-100">
                                        {filteredNotifications.map((notification) => (
                                            <div
                                                key={notification.id}
                                                className={`p-5 transition-colors group cursor-pointer ${notification.unread ? 'bg-blue-50/30' : 'hover:bg-slate-50'}`}
                                                onClick={() => markAsRead(notification.id)}
                                            >
                                                <div className="flex items-start space-x-4">
                                                    <div className="flex-shrink-0 mt-1">
                                                        {getIconForType(notification.type)}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center justify-between mb-1">
                                                            <p className={`text-sm font-medium truncate ${notification.unread ? 'text-slate-900' : 'text-slate-700'}`}>
                                                                {notification.title}
                                                            </p>
                                                            <span className="text-xs text-slate-400 whitespace-nowrap ml-2 flex-shrink-0 flex items-center">
                                <Clock className="w-3 h-3 mr-1" />
                                                                {notification.time}
                              </span>
                                                        </div>
                                                        <p className={`text-sm leading-relaxed ${notification.unread ? 'text-slate-800' : 'text-slate-500'}`}>
                                                            {notification.message}
                                                        </p>

                                                        {/* Action Buttons for standard actions (Meetings) */}
                                                        {notification.actionable && notification.unread && (
                                                            <div className="mt-3 flex space-x-2" onClick={e => e.stopPropagation()}>
                                                                <button className="px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition-colors flex items-center">
                                                                    <Check className="w-3 h-3 mr-1" /> Accept
                                                                </button>
                                                                <button className="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded hover:bg-slate-50 transition-colors flex items-center">
                                                                    <X className="w-3 h-3 mr-1" /> Decline
                                                                </button>
                                                            </div>
                                                        )}

                                                        {/* INLINE ACTION: Grouped Tasks Expansion */}
                                                        {notification.type === 'group' && notification.subTasks && (
                                                            <div className="mt-3" onClick={e => e.stopPropagation()}>
                                                                <button
                                                                    onClick={(e) => toggleGroup(notification.id, e)}
                                                                    className="text-xs font-medium text-emerald-600 hover:text-emerald-700 flex items-center bg-emerald-50 px-2 py-1 rounded"
                                                                >
                                                                    <ChevronDown className={`w-3 h-3 mr-1 transition-transform duration-200 ${expandedGroups[notification.id] ? 'rotate-180' : ''}`} />
                                                                    {expandedGroups[notification.id] ? 'Hide Details' : 'View Details'}
                                                                </button>

                                                                {expandedGroups[notification.id] && (
                                                                    <div className="mt-3 pl-3 border-l-2 border-emerald-100 space-y-2">
                                                                        {notification.subTasks.map(task => (
                                                                            <div key={task.id} className="flex items-center text-xs text-slate-600">
                                                                                <CheckCircle2 className="w-3 h-3 text-emerald-400 mr-2 flex-shrink-0" />
                                                                                <span className="truncate">{task.title}</span>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        )}

                                                        {/* INLINE ACTION: Mention Reply Form */}
                                                        {notification.type === 'mention' && (
                                                            <div className="mt-3" onClick={e => e.stopPropagation()}>
                                                                {replyingTo === notification.id ? (
                                                                    <form onSubmit={(e) => handleReplySubmit(notification.id, e)} className="flex items-center mt-2 shadow-sm rounded-md">
                                                                        <input
                                                                            autoFocus
                                                                            type="text"
                                                                            value={replyText}
                                                                            onChange={(e) => setReplyText(e.target.value)}
                                                                            placeholder="Type your reply..."
                                                                            className="flex-1 text-xs px-3 py-2 border border-slate-300 rounded-l-md focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                                                        />
                                                                        <button
                                                                            type="submit"
                                                                            disabled={!replyText.trim()}
                                                                            className="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white px-3 py-2 rounded-r-md transition-colors border border-blue-600 border-l-0"
                                                                        >
                                                                            <Send className="w-3 h-3" />
                                                                        </button>
                                                                    </form>
                                                                ) : (
                                                                    <button
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            setReplyingTo(notification.id);
                                                                            setReplyText('');
                                                                        }}
                                                                        className="text-xs font-medium text-slate-500 hover:text-blue-600 flex items-center bg-white border border-slate-200 hover:border-blue-200 px-2 py-1 rounded transition-colors"
                                                                    >
                                                                        <CornerDownRight className="w-3 h-3 mr-1" />
                                                                        Quick Reply
                                                                    </button>
                                                                )}
                                                            </div>
                                                        )}

                                                    </div>
                                                    {notification.unread && (
                                                        <div className="flex-shrink-0 w-2 h-2 mt-2 bg-blue-600 rounded-full shadow-sm shadow-blue-200"></div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* --- CHAT PANE --- */}
                    {showChat && (
                        <div className={`flex flex-col bg-slate-50 overflow-hidden ${isFullScreen ? 'w-2/3' : 'w-full'}`}>

                            {/* Context Header Dropdown */}
                            <div className="relative z-20">
                                <button
                                    onClick={() => setShowContextDropdown(!showContextDropdown)}
                                    className="w-full px-5 py-3 bg-white border-b border-slate-200 flex items-center justify-between shadow-sm hover:bg-slate-50 transition-colors"
                                >
                                    <div className="text-left">
                                        <span className="text-xs font-bold text-slate-400 uppercase tracking-wider">{activeContext.type}</span>
                                        <p className="text-sm font-medium text-slate-800 flex items-center mt-0.5">
                                            <span className={`w-2 h-2 rounded-full ${activeContext.color} mr-2`}></span>
                                            {activeContext.name}
                                        </p>
                                    </div>
                                    <div className="relative">
                                        <ChevronDown className={`w-4 h-4 text-slate-400 transition-transform ${showContextDropdown ? 'rotate-180' : ''}`} />
                                        {hasOtherUnreadChats && !showContextDropdown && (
                                            <span className="absolute -top-1 -right-1 w-2 h-2 bg-red-500 border border-white rounded-full"></span>
                                        )}
                                    </div>
                                </button>

                                {/* Dropdown Menu */}
                                {showContextDropdown && (
                                    <div className="absolute top-full left-0 w-full bg-white border-b border-slate-200 shadow-lg max-h-60 overflow-y-auto">
                                        <div className="p-2 text-xs font-semibold text-slate-400 uppercase tracking-wider bg-slate-50">Switch Context</div>
                                        {availableContexts.map(ctx => (
                                            <button
                                                key={ctx.id}
                                                onClick={() => {
                                                    setActiveContext(ctx);
                                                    setShowContextDropdown(false);
                                                    // In a real app, load chat history for the selected context here
                                                }}
                                                className="w-full px-5 py-3 flex items-center hover:bg-blue-50 border-t border-slate-100 first:border-t-0 transition-colors"
                                            >
                                                <span className={`w-2 h-2 rounded-full ${ctx.color} mr-3`}></span>
                                                <div className="text-left flex-1">
                                                    <span className="block text-xs text-slate-400">{ctx.type}</span>
                                                    <span className="block text-sm font-medium text-slate-700">{ctx.name}</span>
                                                </div>
                                                {ctx.unreadCount > 0 && (
                                                    <span className="bg-blue-100 text-blue-700 py-0.5 px-2 rounded-full text-xs font-bold mr-3">
                            {ctx.unreadCount}
                          </span>
                                                )}
                                                {activeContext.id === ctx.id && (
                                                    <Check className="w-4 h-4 text-blue-600" />
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Chat Messages */}
                            <div
                                className="flex-1 overflow-y-auto p-5 space-y-6"
                                onClick={() => setShowContextDropdown(false)} // Close dropdown if clicked outside
                            >
                                {chatMessages.map((msg) => (
                                    <div key={msg.id} className="flex items-start space-x-3">
                                        <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold ${msg.color}`}>
                                            {msg.initials}
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-baseline space-x-2">
                                                <span className="text-sm font-semibold text-slate-900">{msg.user}</span>
                                                <span className="text-xs text-slate-400">{msg.time}</span>
                                            </div>
                                            {msg.type === 'email' ? (
                                                <div className="mt-1 bg-slate-100/80 border border-slate-200 p-3 rounded-lg rounded-tl-none shadow-sm inline-block w-full max-w-sm">
                                                    <div className="flex items-center text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">
                                                        <Mail className="w-3 h-3 mr-1.5" />
                                                        {msg.direction === 'inbound' ? 'Email Received' : 'Email Sent'}
                                                    </div>
                                                    <p className="text-sm text-slate-700 leading-relaxed italic border-l-2 border-slate-300 pl-3 py-1">
                                                        "{msg.summary}"
                                                    </p>
                                                    <button className="mt-3 text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center transition-colors">
                                                        View Full Email <ExternalLink className="w-3 h-3 ml-1" />
                                                    </button>
                                                </div>
                                            ) : (
                                                <p className="text-sm text-slate-700 mt-1 leading-relaxed bg-white p-3 rounded-lg rounded-tl-none shadow-sm border border-slate-100 inline-block">
                                                    {msg.message}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Chat Input */}
                            <div className="p-4 bg-white border-t border-slate-200">
                                <form onSubmit={handleSendMessage} className="relative">
                                    <input
                                        type="text"
                                        value={newMessage}
                                        onChange={(e) => setNewMessage(e.target.value)}
                                        placeholder="Type a message or use @ to mention..."
                                        className="w-full pl-4 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                    />
                                    <button
                                        type="submit"
                                        disabled={!newMessage.trim()}
                                        className="absolute right-2 top-2 p-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:hover:bg-blue-600 transition-colors"
                                    >
                                        <Send className="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    )}

                </div>
            </div>
        </div>
    );
}
