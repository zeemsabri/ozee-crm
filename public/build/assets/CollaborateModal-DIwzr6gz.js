const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["assets/app-DTy8mHo2.js","assets/app-BF589hw9.css"])))=>i.map(i=>d[i]);
import{_ as h}from"./app-DTy8mHo2.js";import x from"./BaseModal-CrlHtl1W.js";import{_}from"./MultiSelectDropdown-DYxc8RwZ.js";import{a as C}from"./presentationsApi-COf6nAvL.js";import{r as a,d as M,y as S,b as k,A as B,o as $,u as A,aG as O,K as m,J as U}from"./vue-entry-C11vZSyV.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./index-DrGCbs5E.js";const T={__name:"CollaborateModal",props:{show:{type:Boolean,default:!1},presentation:{type:Object,required:!0}},emits:["close","updated"],setup(d,{emit:v}){const s=d,r=v,i=a(!1),c=a([]),n=a([]),u=a("editor"),l=a(!1);async function f(){try{i.value=!0;const o=await(typeof window<"u"&&window.axios?window.axios:(await h(async()=>{const{default:t}=await import("./app-DTy8mHo2.js").then(w=>w.e);return{default:t}},__vite__mapDeps([0,1]))).default).get("/api/users");c.value=(o.data||[]).map(t=>({value:t.id,label:t.name?`${t.name} <${t.email}>`:t.email}))}catch{m("Failed to load users")}finally{i.value=!1}}M(()=>{f(),p()});function p(){var o;const e=Array.isArray((o=s.presentation)==null?void 0:o.users)?s.presentation.users:[];n.value=e.map(t=>t.id)}S(()=>{var e;return(e=s.presentation)==null?void 0:e.users},()=>{p()});const b=k(()=>!l.value);async function y(){try{l.value=!0;const e=await C.syncCollaborators(s.presentation.id,n.value,u.value);U("Collaborators updated"),r("updated",(e==null?void 0:e.collaborators)||[]),r("close")}catch{m("Failed to update collaborators")}finally{l.value=!1}}const g=O({name:"CollaborateBody",components:{MultiSelectDropdown:_},template:`
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
  `,setup(){return{users:c,selectedUserIds:n,role:u,saving:l,canSave:b,save:y,closeModal:()=>r("close"),loadingUsers:i}}});return(e,o)=>($(),B(x,{isOpen:d.show,title:"Collaborate",onClose:o[0]||(o[0]=t=>e.$emit("close")),children:A(g)},null,8,["isOpen","children"]))}};export{T as default};
