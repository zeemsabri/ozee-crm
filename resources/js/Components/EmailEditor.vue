<script setup>
import { ref, watch, onMounted } from 'vue';
import {Ckeditor} from "@ckeditor/ckeditor5-vue";
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Type here...',
    },
    height: {
        type: String,
        default: '500px', // CKEditor handles height internally, often with CSS
    },
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(ClassicEditor); // The editor class itself
const editorData = ref(props.modelValue); // Data bound to CKEditor
const editorConfig = ref({
    // CKEditor 5 toolbar configuration
    toolbar: {
        items: [
            'heading', '|',
            'bold', 'italic', 'blockQuote', '|',
            'imageUpload', 'insertTable', 'mediaEmbed', 'codeBlock', '|',
            'undo', 'redo'
        ]
    },
    // CKEditor 5 plugin configuration
    // (Ensure you have imported necessary plugins if using a custom build)
    image: {
        toolbar: [
            'imageTextAlternative',
            'imageStyle:inline',
            'imageStyle:block',
            'imageStyle:side'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn',
            'tableRow',
            'mergeTableCells'
        ]
    },
    // Configure the placeholder
    placeholder: props.placeholder,

    // Custom upload adapter for images
    extraPlugins: [ MyCustomUploadAdapterPlugin ],
});

// Custom Upload Adapter for CKEditor 5
// This class will handle the actual file upload to your backend
class MyUploadAdapter {
    constructor(loader) {
        // The file loader instance to use during the upload.
        this.loader = loader;
        // The URL to send the upload request to.
        this.uploadUrl = '/api/upload-image'; // Your image upload endpoint
    }

    // Starts the upload process.
    upload() {
        return this.loader.file
            .then(file => new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('image', file); // 'image' must match your backend field name

                axios.post(this.uploadUrl, formData, {
                    headers: { 'Content-Type': 'multipart/form-formdata' },
                    onUploadProgress: (progressEvent) => {
                        // Update upload progress for CKEditor's UI
                        const progress = (progressEvent.loaded / progressEvent.total) * 100;
                        this.loader.uploadTotal = progressEvent.total;
                        this.loader.uploaded = progressEvent.loaded;
                        // console.log(`Upload progress: ${progress}%`);
                    }
                })
                    .then(response => {
                        const imageUrl = response.data.url;
                        if (!imageUrl) {
                            console.error('No image URL returned from server:', response.data);
                            return reject('No image URL returned from server.');
                        }
                        console.log('Image URL received from backend:', imageUrl);
                        // Resolve the promise with the URL, which CKEditor then uses to insert the image
                        resolve({
                            default: imageUrl
                        });
                    })
                    .catch(error => {
                        console.error('Image upload failed:', error);
                        let errorMessage = 'Image upload failed.';
                        if (error.response && error.response.data && error.response.data.message) {
                            errorMessage = error.response.data.message;
                        }
                        reject(errorMessage);
                    });
            }));
    }

    // Aborts the upload process.
    abort() {
        // Implement abort logic if needed (e.g., cancel axios request)
        // console.log('Upload aborted.');
    }
}

// Custom Plugin for CKEditor 5 to integrate the upload adapter
function MyCustomUploadAdapterPlugin(editor) {
    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
        return new MyUploadAdapter(loader);
    };
}


// Sync external modelValue changes to CKEditor's data
watch(() => props.modelValue, (newValue) => {
    if (editorData.value !== newValue) {
        editorData.value = newValue;
    }
});

// Emit content changes from CKEditor to modelValue
const onEditorChange = (event) => {
    emit('update:modelValue', editorData.value);
};

// Handle editor ready event (e.g., for initial placeholder setup or other manipulations)
const onEditorReady = (ckeditorInstance) => {
    console.log('CKEditor 5 is ready.', ckeditorInstance);
    // You can access the editor instance here if needed for advanced customization
    // For placeholder, the config.placeholder is usually enough if editor starts empty.
};

// Initial setup on mount if needed (though watch and v-model handle most sync)
onMounted(() => {
    // No specific mounting logic needed here beyond what CKEditor's Vue component handles
});
</script>

<template>
    <div class="ckeditor-wrapper">
        <ckeditor
            :editor="editor"
            v-model="editorData"
            :config="editorConfig"
            @ready="onEditorReady"
            @input="onEditorChange"
            :class="`ckeditor-height-${height.replace('px', '')}`"
        ></ckeditor>
    </div>
</template>

<style>
/* Basic styling for the CKEditor wrapper */
.ckeditor-wrapper {
    @apply border border-gray-300 rounded-md;
    /* CKEditor applies its own styling internally, often overrides */
}

/* You might need to add specific CSS for CKEditor's height */
/* CKEditor 5 does not directly use a 'height' config in the same way TinyMCE does. */
.ck.ck-editor__main .ck-content {
    min-height: v-bind(height); /* Use v-bind to dynamically set min-height based on the prop */
}
/* Add more classes if you have different height props, e.g., ckeditor-height-500px */

</style>
