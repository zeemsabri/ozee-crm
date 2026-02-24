const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["assets/app-nAqQJsbR.js","assets/app-CDDc7bKn.css"])))=>i.map(i=>d[i]);
import{r as o,b as h,m as x,l as _,q as C,o as M,u as S,aD as k,ba as B,M as m,N as $}from"./app-nAqQJsbR.js";import D from"./BaseModal-9nla7ENb.js";import{_ as O}from"./MultiSelectDropdown-BvFQZe28.js";import{a as U}from"./presentationsApi-Db5xHdHc.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";const j={__name:"CollaborateModal",props:{show:{type:Boolean,default:!1},presentation:{type:Object,required:!0}},emits:["close","updated"],setup(d,{emit:v}){const s=d,n=v,r=o(!1),c=o([]),i=o([]),u=o("editor"),l=o(!1);async function f(){try{r.value=!0;const a=await(typeof window<"u"&&window.axios?window.axios:(await B(async()=>{const{default:t}=await import("./app-nAqQJsbR.js").then(w=>w.bg);return{default:t}},__vite__mapDeps([0,1]))).default).get("/api/users");c.value=(a.data||[]).map(t=>({value:t.id,label:t.name?`${t.name} <${t.email}>`:t.email}))}catch{m("Failed to load users")}finally{r.value=!1}}h(()=>{f(),p()});function p(){var a;const e=Array.isArray((a=s.presentation)==null?void 0:a.users)?s.presentation.users:[];i.value=e.map(t=>t.id)}x(()=>{var e;return(e=s.presentation)==null?void 0:e.users},()=>{p()});const b=_(()=>!l.value);async function y(){try{l.value=!0;const e=await U.syncCollaborators(s.presentation.id,i.value,u.value);$("Collaborators updated"),n("updated",(e==null?void 0:e.collaborators)||[]),n("close")}catch{m("Failed to update collaborators")}finally{l.value=!1}}const g=k({name:"CollaborateBody",components:{MultiSelectDropdown:O},template:`
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
  `,setup(){return{users:c,selectedUserIds:i,role:u,saving:l,canSave:b,save:y,closeModal:()=>n("close"),loadingUsers:r}}});return(e,a)=>(M(),C(D,{isOpen:d.show,title:"Collaborate",onClose:a[0]||(a[0]=t=>e.$emit("close")),children:S(g)},null,8,["isOpen","children"]))}};export{j as default};
