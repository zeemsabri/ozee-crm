import React, { useState, useMemo } from 'react';
import {
    AlertCircle,
    Clock,
    CheckCircle2,
    ArrowRight,
    Building2,
    Calendar,
    TrendingUp,
    ChevronRight,
    ShieldCheck,
    Zap,
    Target,
    X,
    Mail,
    Phone,
    Globe,
    MessageSquare,
    MoreVertical,
    ExternalLink,
    ChevronDown
} from 'lucide-react';

// Status mapping updated to include all variations from CSV exports
const STATUS_MAPPING = {
    discovery: {
        leads: ['new', 'inbound', 'assigned', 'cold'],
        enquiries: ['pending_quote', 'enquiry_received']
    },
    engagement: {
        leads: ['contacted', 'meeting_scheduled', 'warm'],
        enquiries: ['quoted', 'proposal_sent']
    },
    proposal: {
        leads: ['qualified', 'negotiation', 'awaiting_feedback'],
        enquiries: ['awaiting_review']
    },
    closing: {
        leads: ['converted', 'won', 'signed'],
        enquiries: ['approved', 'converted_to_service']
    }
};

const MOCK_DATA = [
    // --- Data from leads.csv ---
    {
        id: 1,
        card_type: 'lead',
        first_name: 'Nick',
        last_name: 'Vidal-Hall',
        email: 'nick@vidal-hallphotography.co.uk',
        company: 'Vidal Hall',
        status: 'contacted',
        estimated_value: 100.00,
        currency: 'USD',
        updated_at: '2026-04-07 07:13:20',
        next_follow_up_date: null,
        notes: 'Client is coming from a reference of an Upwork contract',
        website: 'vidal-hallphotography.co.uk'
    },
    {
        id: 2,
        card_type: 'lead',
        first_name: 'Sue',
        last_name: 'Stagg',
        email: 'admin@thekeeptasmania.com.au',
        phone: '+61408484132',
        company: 'The Keep Tasmania',
        status: 'converted',
        estimated_value: 1155.00,
        currency: 'USD',
        updated_at: '2025-09-11 04:05:00',
        next_follow_up_date: null,
        website: 'https://www.thekeeptasmania.com.au/'
    },
    {
        id: 3,
        card_type: 'lead',
        first_name: 'Benjamin',
        last_name: 'Castledine',
        email: 'ben@smarteroutdoors.com.au',
        company: 'Smarter Outdoors',
        status: 'new',
        estimated_value: 0.00,
        currency: 'AUD',
        updated_at: '2025-09-04 05:18:16',
        next_follow_up_date: null
    },
    {
        id: 113,
        card_type: 'lead',
        first_name: 'Jashandeep',
        last_name: 'Singh',
        email: 'darwin.sydney247care@gmail.com',
        phone: '+61419789624',
        company: 'Sydney 24/7 Care',
        status: 'contacted',
        estimated_value: 2500.00,
        currency: 'USD',
        updated_at: '2026-04-12 02:14:10',
        next_follow_up_date: null,
        website: 'http://www.sydney247care.com.au/'
    },
    {
        id: 114,
        card_type: 'lead',
        first_name: 'Jashandeep',
        last_name: 'Singh',
        email: 'jashandeep201@gmail.com',
        phone: '455355481',
        company: 'Sydney 24/7 care',
        status: 'qualified',
        estimated_value: 4800.00,
        currency: 'USD',
        updated_at: '2026-04-12 02:16:59',
        next_follow_up_date: '2026-04-10 09:11:07', // Overdue trigger
        notes: 'Website And Business Email required.'
    },
    {
        id: 128,
        card_type: 'lead',
        first_name: 'Luke',
        last_name: 'Rainone',
        email: 'luke@allureadvisory.com.au',
        company: 'Allure Advisory',
        status: 'new',
        estimated_value: 15000.00,
        currency: 'AUD',
        updated_at: '2026-04-11 12:00:00',
        next_follow_up_date: null
    },

    // --- Data Projected from projects.csv (service_details JSON) ---
    // Representing rows where service_tracking_type=client_enquiry & show_on_leads_board=true
    {
        id: 2001,
        card_type: 'client_enquiry',
        client_name: 'MMS IT Solutions',
        project_name: 'Demo Project',
        enquiry_status: 'pending_quote',
        amount: 123.00,
        currency: 'AUD',
        enquiry_updated_at: '2026-04-08 16:22:18',
        description: 'Website Designing service enquiry (Enquiry ID: 9aa92be6-44d3-4fd1-a9a5-9fb80ad48b28)'
    },
    {
        id: 7901,
        card_type: 'client_enquiry',
        client_name: 'Australian Hairdresser',
        project_name: 'Trim & Trend',
        enquiry_status: 'quoted',
        amount: 2500.00,
        currency: 'AUD',
        enquiry_updated_at: '2026-04-05 16:43:38',
        description: 'Branding Identity Concept Refinement - To help establish a clearer brand direction.'
    }
];

const STAGES = [
    { id: 'discovery', name: 'Discovery', color: 'indigo', description: 'New leads & initial enquiries' },
    { id: 'engagement', name: 'Engagement', color: 'blue', description: 'Actively communicating/quoting' },
    { id: 'proposal', name: 'Proposal / Qualified', color: 'purple', description: 'Negotiation & vetting' },
    { id: 'closing', name: 'Approved / Converted', color: 'emerald', description: 'Success & handoff' }
];

const LeadsCommandCenter = ({ leads = MOCK_DATA, onEdit, onConvert }) => {
    const [activeStage, setActiveStage] = useState(null);
    const [selectedItem, setSelectedItem] = useState(null);

    const pipelineData = useMemo(() => {
        return STAGES.map(stage => {
            const mapping = STATUS_MAPPING[stage.id];

            const stageItems = leads.filter(item => {
                const isEnquiry = item.card_type === 'client_enquiry';
                const currentStatus = (isEnquiry ? item.enquiry_status : item.status)?.toLowerCase();

                if (isEnquiry) {
                    return mapping.enquiries.includes(currentStatus);
                }
                return mapping.leads.includes(currentStatus);
            }).map(item => {
                const isEnquiry = item.card_type === 'client_enquiry';
                const lastUpdateStr = item.updated_at || item.enquiry_updated_at || item.created_at;
                const lastUpdate = new Date(lastUpdateStr.replace(' ', 'T'));
                const daysStale = Math.floor((new Date() - lastUpdate) / (1000 * 60 * 60 * 24));

                const isOverdue = item.next_follow_up_date && new Date(item.next_follow_up_date.replace(' ', 'T')) < new Date();
                const needsAttention = isOverdue || (item.status === 'new' && daysStale > 2) || (item.enquiry_status === 'pending_quote' && daysStale > 1);

                return { ...item, isEnquiry, daysStale, needsAttention };
            });

            const totalValue = stageItems.reduce((acc, curr) => acc + (Number(curr.estimated_value || curr.amount || 0)), 0);
            const criticalCount = stageItems.filter(i => i.needsAttention).length;

            return { ...stage, items: stageItems, totalValue, criticalCount };
        });
    }, [leads]);

    const grandTotal = pipelineData.reduce((acc, curr) => acc + curr.totalValue, 0);

    return (
        <div className="flex h-screen bg-slate-50 font-sans overflow-hidden">
            {/* Main Content Area */}
            <div className={`flex-1 flex flex-col p-6 transition-all duration-300 ${selectedItem ? 'mr-96' : ''}`}>
                {/* Header */}
                <div className="flex items-center justify-between mb-8">
                    <div className="flex items-center gap-4">
                        <div className="p-3 bg-slate-900 rounded-2xl shadow-lg shadow-slate-200">
                            <Target className="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-black text-slate-900 tracking-tight">Pipeline Pulse</h1>
                            <p className="text-sm font-medium text-slate-500">Integrated CRM & Upsell Forecasting</p>
                        </div>
                    </div>

                    <div className="flex gap-6 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                        <div className="text-right">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Global Weighted Value</p>
                            <p className="text-2xl font-black text-slate-900 leading-none">${grandTotal.toLocaleString()}</p>
                        </div>
                        <div className="h-10 w-[1px] bg-slate-200" />
                        <div className="text-right">
                            <p className="text-[10px] font-black text-rose-400 uppercase tracking-widest">Urgent Actions</p>
                            <p className="text-2xl font-black text-rose-600 leading-none">
                                {pipelineData.reduce((acc, curr) => acc + curr.criticalCount, 0)}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Funnel Stages */}
                <div className="grid grid-cols-4 gap-4 mb-8">
                    {pipelineData.map((stage, idx) => (
                        <div
                            key={stage.id}
                            onClick={() => setActiveStage(stage.id === activeStage ? null : stage.id)}
                            className={`relative group cursor-pointer transition-all duration-300 ${
                                activeStage && activeStage !== stage.id ? 'opacity-40 scale-[0.98]' : 'scale-100'
                            }`}
                        >
                            <div className={`p-5 rounded-3xl border-2 transition-all h-full ${
                                activeStage === stage.id
                                    ? `bg-white border-slate-900 shadow-xl ring-8 ring-slate-100`
                                    : 'bg-white border-transparent shadow-sm hover:shadow-md hover:border-slate-200'
                            }`}>
                                <div className="flex justify-between items-start mb-4">
                                    <div className={`w-8 h-8 rounded-xl flex items-center justify-center bg-${stage.color}-100 text-${stage.color}-600`}>
                                        <ChevronDown className="w-4 h-4 transform -rotate-90" />
                                    </div>
                                    {stage.criticalCount > 0 && (
                                        <span className="flex h-6 w-6 items-center justify-center bg-rose-500 text-white text-[10px] font-black rounded-full shadow-lg shadow-rose-200 animate-bounce">
                      {stage.criticalCount}
                    </span>
                                    )}
                                </div>

                                <h3 className="text-sm font-black text-slate-400 uppercase tracking-widest mb-1">{stage.name}</h3>
                                <div className="flex items-baseline gap-1">
                                    <span className="text-2xl font-black text-slate-900">${(stage.totalValue / 1000).toFixed(1)}k</span>
                                    <span className="text-xs font-bold text-slate-400">({stage.items.length})</span>
                                </div>

                                <p className="text-[10px] text-slate-400 mt-2 font-medium italic leading-tight">{stage.description}</p>

                                <div className="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        className={`h-full bg-slate-900 transition-all duration-1000`}
                                        style={{ width: `${(idx + 1) * 25}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Combined Feed */}
                <div className="flex-1 overflow-hidden flex flex-col">
                    <div className="flex items-center justify-between mb-4 px-2">
                        <h2 className="text-xs font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                            <Zap className="w-4 h-4 text-amber-500 fill-amber-500" />
                            {activeStage ? `Focussed on: ${STAGES.find(s => s.id === activeStage).name}` : 'Priority Workflow Feed'}
                        </h2>
                        {activeStage && (
                            <button
                                onClick={() => setActiveStage(null)}
                                className="text-[10px] font-bold text-blue-600 hover:underline uppercase"
                            >
                                Clear Filter
                            </button>
                        )}
                    </div>

                    <div className="flex-1 overflow-y-auto pr-2 space-y-3 custom-scrollbar">
                        {(activeStage
                                ? pipelineData.find(s => s.id === activeStage).items
                                : pipelineData.flatMap(s => s.items).sort((a, b) => (b.needsAttention ? 1 : -1) - (a.needsAttention ? 1 : -1))
                        ).map((item) => (
                            <div
                                key={`${item.card_type}-${item.id}`}
                                onClick={() => setSelectedItem(item)}
                                className={`flex items-center gap-4 p-4 rounded-2xl border transition-all cursor-pointer group bg-white shadow-sm hover:translate-x-1 ${
                                    selectedItem?.id === item.id && selectedItem?.card_type === item.card_type
                                        ? 'border-slate-900 ring-4 ring-slate-100'
                                        : item.needsAttention
                                            ? 'border-rose-100 bg-rose-50/20 shadow-rose-50/50'
                                            : 'border-slate-100'
                                }`}
                            >
                                <div className={`w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 shadow-sm ${
                                    item.isEnquiry ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'
                                }`}>
                                    {item.isEnquiry ? <Building2 className="w-6 h-6" /> : <TrendingUp className="w-6 h-6" />}
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-0.5">
                    <span className="font-bold text-slate-900 truncate text-[15px]">
                      {item.isEnquiry ? item.client_name : `${item.first_name} ${item.last_name}`}
                    </span>
                                        {item.needsAttention && (
                                            <span className="flex items-center gap-1 text-[9px] font-black text-rose-600 uppercase bg-rose-100 px-2 py-0.5 rounded-md">
                        <AlertCircle className="w-2.5 h-2.5" />
                        Urgent
                      </span>
                                        )}
                                    </div>
                                    <div className="text-xs text-slate-500 flex items-center gap-2 font-medium italic">
                                        <span className="text-slate-400 font-bold uppercase tracking-tighter">{(item.enquiry_status || item.status).replace(/_/g, ' ')}</span>
                                        <span className="text-slate-300">•</span>
                                        <span className="truncate">{item.isEnquiry ? item.project_name : (item.company || 'Private Lead')}</span>
                                    </div>
                                </div>

                                <div className="text-right">
                                    <p className="text-[16px] font-black text-slate-900 leading-none mb-1">
                                        {(item.currency === 'AUD' ? 'A$' : '$')}{(Number(item.estimated_value || item.amount || 0)).toLocaleString()}
                                    </p>
                                    <p className={`text-[9px] font-black uppercase tracking-widest ${
                                        item.isEnquiry ? 'text-purple-500' : 'text-blue-500'
                                    }`}>
                                        {item.isEnquiry ? 'Existing Client' : 'New Acquisition'}
                                    </p>
                                </div>

                                <div className="pl-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <ArrowRight className="w-5 h-5 text-slate-300" />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Detail Sidebar */}
            <div
                className={`fixed top-0 right-0 h-full w-96 bg-white border-l border-slate-200 shadow-2xl transform transition-transform duration-300 z-50 flex flex-col ${
                    selectedItem ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                {selectedItem && (
                    <>
                        <div className="p-6 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h2 className="font-black text-slate-900 tracking-tight">Lead Intelligence</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    {selectedItem.card_type.replace('_', ' ')} • ID: {selectedItem.id}
                                </p>
                            </div>
                            <button onClick={() => setSelectedItem(null)} className="p-2 hover:bg-slate-100 rounded-xl transition-colors">
                                <X className="w-5 h-5 text-slate-400" />
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-6 space-y-8">
                            <div className="space-y-1">
                                <h3 className="text-2xl font-black text-slate-900 leading-tight">
                                    {selectedItem.isEnquiry ? selectedItem.client_name : `${selectedItem.first_name} ${selectedItem.last_name}`}
                                </h3>
                                <p className="text-blue-600 font-bold flex items-center gap-1.5">
                                    {selectedItem.isEnquiry ? selectedItem.project_name : selectedItem.company}
                                    {!selectedItem.isEnquiry && selectedItem.website && <ExternalLink className="w-3 h-3" />}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="bg-slate-50 rounded-2xl p-4 border border-slate-100 text-center">
                                    <p className="text-[9px] font-black text-slate-400 uppercase mb-1 tracking-widest">Stage</p>
                                    <p className="text-xs font-black text-slate-700 uppercase">{(selectedItem.enquiry_status || selectedItem.status).replace('_', ' ')}</p>
                                </div>
                                <div className="bg-slate-50 rounded-2xl p-4 border border-slate-100 text-center">
                                    <p className="text-[9px] font-black text-slate-400 uppercase mb-1 tracking-widest">Potential</p>
                                    <p className="text-xs font-black text-slate-700">{(selectedItem.currency === 'AUD' ? 'A$' : '$')}{(Number(selectedItem.estimated_value || selectedItem.amount || 0)).toLocaleString()}</p>
                                </div>
                            </div>

                            {!selectedItem.isEnquiry && (
                                <div className="space-y-4 pt-4 border-t border-slate-100">
                                    <h4 className="text-[11px] font-black text-slate-400 uppercase tracking-widest">Contact Info</h4>
                                    <div className="space-y-3">
                                        {selectedItem.email && (
                                            <div className="flex items-center gap-3 text-sm text-slate-600 group cursor-pointer hover:text-blue-600">
                                                <div className="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                                                    <Mail className="w-4 h-4" />
                                                </div>
                                                <span className="font-medium truncate">{selectedItem.email}</span>
                                            </div>
                                        )}
                                        {selectedItem.phone && (
                                            <div className="flex items-center gap-3 text-sm text-slate-600 group cursor-pointer hover:text-blue-600">
                                                <div className="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                                                    <Phone className="w-4 h-4" />
                                                </div>
                                                <span className="font-medium">{selectedItem.phone}</span>
                                            </div>
                                        )}
                                        {selectedItem.website && (
                                            <div className="flex items-center gap-3 text-sm text-slate-600 group cursor-pointer hover:text-blue-600">
                                                <div className="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                                                    <Globe className="w-4 h-4" />
                                                </div>
                                                <span className="font-medium truncate">{selectedItem.website}</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {(selectedItem.notes || selectedItem.description) && (
                                <div className="p-4 bg-amber-50 rounded-2xl border border-amber-100 mt-4">
                                    <h4 className="text-[10px] font-black text-amber-600 uppercase mb-2 flex items-center gap-1.5">
                                        <MessageSquare className="w-3 h-3" /> Note from Acquisition
                                    </h4>
                                    <p className="text-xs text-amber-900 leading-relaxed font-medium">"{selectedItem.notes || selectedItem.description}"</p>
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="space-y-3 pt-6 border-t border-slate-100">
                                {selectedItem.enquiry_status === 'approved' || selectedItem.status === 'won' || selectedItem.status === 'converted' ? (
                                    <button className="w-full py-4 bg-emerald-600 text-white rounded-2xl text-xs font-black hover:bg-emerald-700 transition-all flex items-center justify-center gap-2 shadow-lg shadow-emerald-100">
                                        <CheckCircle2 className="w-4 h-4" /> CONVERT TO SERVICE
                                    </button>
                                ) : (
                                    <button className="w-full py-4 bg-slate-900 text-white rounded-2xl text-xs font-black hover:bg-slate-800 transition-all flex items-center justify-center gap-2 shadow-lg shadow-slate-200">
                                        <Zap className="w-4 h-4 text-amber-400" /> PROCEED TO NEXT STAGE
                                    </button>
                                )}
                                <button className="w-full py-4 bg-white border-2 border-slate-900 text-slate-900 rounded-2xl text-xs font-black hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                                    <Calendar className="w-4 h-4" /> SCHEDULE FOLLOW-UP
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </div>

            {selectedItem && <div onClick={() => setSelectedItem(null)} className="fixed inset-0 bg-slate-900/10 backdrop-blur-sm z-40" />}
        </div>
    );
};

export default LeadsCommandCenter;
