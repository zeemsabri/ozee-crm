const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["assets/app-CAFAb1eF.js","assets/app-DI37tYMV.css"])))=>i.map(i=>d[i]);
import{r as o,f as h,e as x,c as _,g as C,o as M,u as S,aB as k,aV as B,J as m,N as $}from"./app-CAFAb1eF.js";import O from"./BaseModal-B3dAOXul.js";import{_ as U}from"./MultiSelectDropdown-DVJL8bN_.js";import{a as A}from"./presentationsApi-l04Nk9tx.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./ChevronDownIcon-BrKdHfGB.js";const j={__name:"CollaborateModal",props:{show:{type:Boolean,default:!1},presentation:{type:Object,required:!0}},emits:["close","updated"],setup(c,{emit:v}){const s=c,r=v,n=o(!1),d=o([]),i=o([]),u=o("editor"),l=o(!1);async function f(){try{n.value=!0;const a=await(typeof window<"u"&&window.axios?window.axios:(await B(async()=>{const{default:t}=await import("./app-CAFAb1eF.js").then(w=>w.b5);return{default:t}},__vite__mapDeps([0,1]))).default).get("/api/users");d.value=(a.data||[]).map(t=>({value:t.id,label:t.name?`${t.name} <${t.email}>`:t.email}))}catch{m("Failed to load users")}finally{n.value=!1}}h(()=>{f(),p()});function p(){var a;const e=Array.isArray((a=s.presentation)==null?void 0:a.users)?s.presentation.users:[];i.value=e.map(t=>t.id)}x(()=>{var e;return(e=s.presentation)==null?void 0:e.users},()=>{p()});const b=_(()=>!l.value);async function y(){try{l.value=!0;const e=await A.syncCollaborators(s.presentation.id,i.value,u.value);$("Collaborators updated"),r("updated",(e==null?void 0:e.collaborators)||[]),r("close")}catch{m("Failed to update collaborators")}finally{l.value=!1}}const g=k({name:"CollaborateBody",components:{MultiSelectDropdown:U},template:`
    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Select collaborators</label>
        <MultiSelectDropdown
          v-model="selectedUserIds"
          :options="users"
          :isMulti="true"
          placeholder="Search and select users"
        />
        <p class="text-xs text-gray-500 mt-1">You can add multiple users. They will get access to view/edit this presentation.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
        <select v-model="role" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm mt-1 block w-full px-3 py-2">
          <option value="editor">Editor</option>
          <option value="viewer">Viewer</option>
        </select>
      </div>

      <div class="flex justify-end gap-2">
        <button @click="closeModal" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Cancel</button>
        <button :disabled="!canSave" @click="save" class="px-3 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 text-sm disabled:opacity-50">
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>
  `,setup(){return{users:d,selectedUserIds:i,role:u,saving:l,canSave:b,save:y,closeModal:()=>r("close"),loadingUsers:n}}});return(e,a)=>(M(),C(O,{isOpen:c.show,title:"Collaborate",onClose:a[0]||(a[0]=t=>e.$emit("close")),children:S(g)},null,8,["isOpen","children"]))}};export{j as default};
