import{r as m,b as n,c as g,o as d,a as r,t as h,f as b,g as w,u as x,v as k,n as _,q as y,m as j}from"./app-DMkA-N6z.js";import{_ as E}from"./_plugin-vue_export-helper-DlAUqK2U.js";import{r as C}from"./MagnifyingGlassIcon-B67jrMPd.js";import{r as O}from"./CheckIcon-BInJAwfe.js";import{r as S}from"./ClipboardDocumentIcon-B51vmdGX.js";const L={class:"flex flex-col bg-slate-900 rounded-xl border border-slate-800 shadow-xl overflow-hidden relative group/json"},A={class:"flex flex-col sm:flex-row sm:items-center justify-between px-3 py-2 bg-slate-800/80 border-b border-slate-700/50 gap-2"},N={class:"flex items-center gap-3"},J={class:"text-xs font-bold text-slate-400 uppercase tracking-widest pl-1"},V={class:"flex items-center justify-end gap-2 w-full sm:w-auto"},B={class:"relative w-full sm:w-64 text-slate-300"},K={class:"hidden sm:inline"},D={class:"p-4 pl-6 overflow-auto max-h-[500px] bg-[#0d1117] text-slate-300 custom-scrollbar"},M={key:1,class:"text-slate-500 italic text-xs py-2 text-center"},T={name:"JsonNode",props:{nodeKey:{type:[String,Number],default:""},value:{required:!0},isLast:{type:Boolean,default:!0},depth:{type:Number,default:0},search:{type:String,default:""},forceExpand:{type:Boolean,default:!1}},setup(e){const t=m(e.depth<3||e.forceExpand);j(()=>e.forceExpand,i=>{i&&(t.value=!0)});const o=()=>t.value=!t.value,s=n(()=>e.value!==null&&typeof e.value=="object"),a=n(()=>Array.isArray(e.value)),f=n(()=>e.value===null?"text-slate-500":typeof e.value=="string"?"text-emerald-400":typeof e.value=="number"?"text-amber-400":typeof e.value=="boolean"?"text-pink-400":"text-indigo-300"),l=n(()=>e.value===null?"null":typeof e.value=="string"?`"${e.value}"`:String(e.value)),c=n(()=>{if(!e.search)return!1;const i=e.search.toLowerCase();return!!(String(e.nodeKey).toLowerCase().includes(i)||!s.value&&l.value.toLowerCase().includes(i))}),v=n(()=>!e.search||c.value?!0:s.value?Object.entries(e.value).some(([i,u])=>{const p=e.search.toLowerCase();return i.toLowerCase().includes(p)||u!==null&&typeof u!="object"&&String(u).toLowerCase().includes(p)?!0:JSON.stringify(u).toLowerCase().includes(p)}):!1);return{isExpanded:t,toggle:o,isObject:s,isArray:a,typeClass:f,displayValue:l,hasMatch:c,shouldShow:v}},template:`
        <div v-show="shouldShow" class="font-mono text-[11px] sm:text-xs leading-relaxed" :class="{'bg-indigo-500/20 rounded px-1': hasMatch && search}">
            <div class="flex items-start group">
                <!-- Indent & Toggle -->
                <div class="flex items-center shrink-0 w-4 -ml-4" @click="isObject && toggle()">
                    <svg v-if="isObject" class="w-3 h-3 text-slate-500 cursor-pointer hover:text-slate-300 transition-transform" :class="{'rotate-90': isExpanded}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>

                <!-- Key -->
                <div class="flex flex-wrap items-baseline min-w-0">
                    <span v-if="nodeKey !== ''" class="text-sky-300 mr-1.5 font-semibold">"{{ nodeKey }}":</span>

                    <!-- Object/Array Start -->
                    <template v-if="isObject">
                        <span class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? '[' : '{' }}</span>
                        
                        <span v-if="!isExpanded" class="text-slate-500 mx-2 cursor-pointer select-none" @click="toggle()">
                            {{ isArray ? \`\${value.length} items\` : '...' }}
                        </span>
                        
                        <span v-if="!isExpanded" class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? ']' : '}' }}<span v-if="!isLast">,</span></span>
                    </template>

                    <!-- Primitive Value -->
                    <template v-else>
                        <span :class="typeClass" class="break-all">{{ displayValue }}</span>
                        <span v-if="!isLast" class="text-slate-400">,</span>
                    </template>
                </div>
            </div>

            <!-- Object/Array Children -->
            <div v-if="isObject && isExpanded" class="ml-4 pl-2 border-l border-slate-700/50">
                <JsonNode 
                    v-for="(val, key, index) in value" 
                    :key="key"
                    :nodeKey="isArray ? '' : key"
                    :value="val"
                    :isLast="index === Object.keys(value).length - 1"
                    :depth="depth + 1"
                    :search="search"
                    :forceExpand="!!search || forceExpand"
                />
            </div>
            
            <!-- Object/Array End -->
            <div v-if="isObject && isExpanded" class="flex items-center">
                <span class="text-slate-400 cursor-pointer" @click="toggle()">{{ isArray ? ']' : '}' }}<span v-if="!isLast">,</span></span>
            </div>
        </div>
    `},$={__name:"JsonViewerV2",props:{data:{type:[Object,Array,String],default:null},title:{type:String,default:"JSON Data"},startExpanded:{type:Boolean,default:!0}},setup(e){const t=e,o=m(""),s=m(!1),a=n(()=>{if(typeof t.data=="string")try{return JSON.parse(t.data)}catch{return t.data}return t.data}),f=async()=>{try{const l=typeof a.value=="string"?a.value:JSON.stringify(a.value,null,2);await navigator.clipboard.writeText(l),s.value=!0,setTimeout(()=>s.value=!1,2e3)}catch(l){console.error("Copy failed",l)}};return(l,c)=>(d(),g("div",L,[r("div",A,[r("div",N,[r("span",J,h(e.title),1)]),r("div",V,[r("div",B,[b(x(C),{class:"w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 opacity-50"}),w(r("input",{"onUpdate:modelValue":c[0]||(c[0]=v=>o.value=v),placeholder:"Filter keys or values...",class:"w-full bg-slate-900 border border-slate-700 text-xs rounded-lg pl-8 pr-3 py-1.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 placeholder-slate-500 transition-colors"},null,512),[[k,o.value]])]),r("button",{onClick:f,class:_(["flex items-center shrink-0 justify-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors",s.value?"bg-emerald-500/20 text-emerald-400":"bg-slate-700 text-slate-300 hover:bg-slate-600"])},[s.value?(d(),y(x(O),{key:0,class:"w-3.5 h-3.5"})):(d(),y(x(S),{key:1,class:"w-3.5 h-3.5"})),r("span",K,h(s.value?"Copied":"Copy"),1)],2)])]),r("div",D,[a.value!==null&&a.value!==void 0&&a.value!==""?(d(),y(T,{key:0,value:a.value,search:o.value,forceExpand:!!o.value||e.startExpanded,isLast:!0},null,8,["value","search","forceExpand"])):(d(),g("div",M," No data or empty "))])]))}},Q=E($,[["__scopeId","data-v-b5a827e1"]]);export{Q as default};
