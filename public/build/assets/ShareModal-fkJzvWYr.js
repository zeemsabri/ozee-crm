import d from"./BaseModal-pw9rACkZ.js";import{r as u,b as f,aP as m,A as g,o as h,u as y,aG as v}from"./vue-entry-BuXD8SUy.js";import"./_plugin-vue_export-helper-DlAUqK2U.js";import"./app-BzaI5W4L.js";import"./index-C1D1FjvJ.js";const C={__name:"ShareModal",props:{show:{type:Boolean,default:!1},presentation:{type:Object,default:null},shareUrl:{type:String,default:""}},emits:["close"],setup(r,{emit:s}){const t=r,i=s,o=u(!1),n=f(()=>{var l;if(t.shareUrl)return t.shareUrl;const a=((l=t.presentation)==null?void 0:l.share_token)||"",e=typeof window<"u"?window.location.origin:"";return a?`${e}/view/${a}`:`${e}/view`});function c(){n.value&&navigator.clipboard.writeText(n.value).then(()=>{o.value=!0,setTimeout(()=>o.value=!1,1500)})}m(()=>{t.show||(o.value=!1)});const p=v({name:"ShareModalBody",template:`
    <div class="space-y-4">
      <p class="text-sm text-gray-600">Anyone with this link can view the presentation.</p>
      <div class="flex items-center gap-2">
        <input
          :value="fullUrl"
          class="flex-1 border border-gray-200 rounded-lg p-2 bg-gray-50 text-gray-800 select-all"
          readonly
          aria-label="Shareable URL"
        />
        <button
          @click="copy"
          class="px-3 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 text-sm whitespace-nowrap"
        >
          {{ copied ? 'Copied ✓' : 'Copy Link' }}
        </button>
      </div>
      <div class="flex justify-end">
        <button @click="closeModal" class="px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm">Close</button>
      </div>
    </div>
  `,setup(){return{fullUrl:n,copied:o,copy:c,closeModal:()=>i("close")}}});return(a,e)=>(h(),g(d,{isOpen:r.show,title:"Share Presentation",onClose:e[0]||(e[0]=l=>a.$emit("close")),children:y(p)},null,8,["isOpen","children"]))}};export{C as default};
