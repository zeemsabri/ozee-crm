import React, { useState, useEffect } from 'react';
import { initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously, signInWithCustomToken, onAuthStateChanged } from 'firebase/auth';
import { getFirestore, doc, addDoc, onSnapshot, collection, query, where, serverTimestamp, updateDoc } from 'firebase/firestore'; // Import updateDoc

// Define global variables for Firebase configuration provided by the environment
const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';
const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : {};
const initialAuthToken = typeof __initial_auth_token !== 'undefined' ? __initial_auth_token : null;

// Helper to generate Google Drive embed URL
const getGoogleDriveEmbedUrl = (id) => {
    // This is a placeholder. Replace with actual embed URLs for your content types.
    // For Google Docs: https://docs.google.com/document/d/YOUR_DOC_ID/preview
    // For Google Slides: https://docs.google.com/presentation/d/YOUR_SLIDE_ID/embed?start=false&loop=false&delayms=3000
    // For a generic image/PDF in Drive: https://drive.google.com/file/d/YOUR_FILE_ID/preview
    return `https://docs.google.com/document/d/${id}/preview`; // Example for Google Docs
};

// --- DocumentDetail Component (for individual document view) ---
function DocumentDetail({ db, isAuthReady, currentDocument, magicToken, onBackToDashboard }) {
    const [comments, setComments] = useState([]);
    const [clientName, setClientName] = useState('');
    const [clientComment, setClientComment] = useState('');
    const [message, setMessage] = useState('');
    const [docStatus, setDocStatus] = useState(currentDocument?.status || 'Pending Review'); // Track status locally

    // Listen for real-time comments from Firestore for the specific document
    useEffect(() => {
        if (db && isAuthReady && currentDocument?.googleDriveId) {
            // Ensure currentDocument.googleDriveId is just the ID, not a full URL
            const commentsCollectionRef = collection(db, `artifacts/${appId}/public/data/documentComments/${currentDocument.googleDriveId}/comments`);
            const q = query(commentsCollectionRef);

            const unsubscribe = onSnapshot(q, (snapshot) => {
                const fetchedComments = [];
                snapshot.forEach((doc) => {
                    fetchedComments.push({ id: doc.id, ...doc.data() });
                });
                // Sort comments by timestamp, latest first
                fetchedComments.sort((a, b) => (b.timestamp?.toDate() || 0) - (a.timestamp?.toDate() || 0));
                setComments(fetchedComments);
            }, (error) => {
                console.error("Error fetching comments:", error);
                setMessage(`Error fetching comments: ${error.message}`);
            });

            // Also listen for changes to the document's status in the main collection
            const docRef = doc(db, `artifacts/${appId}/public/data/clientPortalDocuments`, currentDocument.id);
            const unsubscribeDocStatus = onSnapshot(docRef, (docSnap) => {
                if (docSnap.exists()) {
                    setDocStatus(docSnap.data().status);
                }
            }, (error) => {
                console.error("Error fetching document status:", error);
            });


            return () => {
                unsubscribe();
                unsubscribeDocStatus();
            };
        }
    }, [db, isAuthReady, currentDocument, appId]);

    const handleCommentSubmit = async (e) => {
        e.preventDefault();
        if (!clientComment.trim()) {
            setMessage('Please enter a comment.');
            return;
        }
        if (!isAuthReady || !db || !currentDocument?.googleDriveId) {
            setMessage('Application is not ready. Please wait or refresh.');
            return;
        }

        try {
            const commentsCollectionRef = collection(db, `artifacts/${appId}/public/data/documentComments/${currentDocument.googleDriveId}/comments`);
            await addDoc(commentsCollectionRef, {
                name: clientName.trim() || 'Anonymous Client',
                commentText: clientComment.trim(),
                timestamp: serverTimestamp(),
            });
            setMessage('Comment submitted successfully!');
            setClientComment('');
            // In a real scenario, you'd then trigger your Google Chat Service via your backend
            // to send a reply to the relevant thread.
        } catch (error) {
            console.error("Error adding comment:", error);
            setMessage(`Error submitting comment: ${error.message}`);
        }
    };

    const handleApprove = async () => {
        if (!isAuthReady || !db || !currentDocument?.id) {
            setMessage('Application is not ready or document ID is missing.');
            return;
        }
        if (docStatus === 'Approved') {
            setMessage('This document is already approved.');
            return;
        }

        try {
            const docRef = doc(db, `artifacts/${appId}/public/data/clientPortalDocuments`, currentDocument.id);
            await updateDoc(docRef, {
                status: 'Approved',
                lastUpdatedAt: serverTimestamp(),
            });
            setMessage('Document approved successfully!');
            setDocStatus('Approved'); // Update local state immediately for UI responsiveness
            // In a real scenario, you'd trigger your Google Chat Service via your backend
            // to send an "Approved" message to the relevant thread.
        } catch (error) {
            console.error("Error approving document:", error);
            setMessage(`Error approving document: ${error.message}`);
        }
    };

    if (!currentDocument) {
        return (
            <div className="text-center p-8 bg-white rounded-md shadow-xl max-w-4xl mx-auto mt-8">
                <p className="text-gray-600 text-lg">No document selected or found.</p>
                <button
                    onClick={onBackToDashboard}
                    className="mt-6 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Back to Dashboard
                </button>
            </div>
        );
    }

    return (
        <div className="w-full max-w-4xl bg-white rounded-lg shadow-xl p-6 sm:p-8">
            <button
                onClick={onBackToDashboard}
                className="mb-6 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </button>

            <h1 className="text-3xl sm:text-4xl font-extrabold text-indigo-700 mb-4 text-center">
                {currentDocument.title || 'Content Review'}
            </h1>
            <p className="text-sm text-gray-500 mb-6 text-center">
                Document ID: <span className="font-mono bg-gray-100 px-2 py-1 rounded">{currentDocument.googleDriveId || 'N/A'}</span>
                <span className={`ml-4 px-3 py-1 rounded-full text-sm font-medium ${
                    docStatus === 'Approved' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'
                }`}>
            Status: {docStatus}
        </span>
            </p>

            {message && (
                <div className={`p-3 mb-4 rounded-md ${message.startsWith('Error') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} text-center`}>
                    {message}
                </div>
            )}

            <div className="mb-8">
                <h2 className="text-2xl font-bold text-gray-800 mb-3">Content for Review</h2>
                <div className="relative pt-[56.25%] overflow-hidden rounded-md shadow-md bg-gray-200"> {/* 16:9 aspect ratio */}
                    <iframe
                        src={getGoogleDriveEmbedUrl(currentDocument.googleDriveId)}
                        className="absolute top-0 left-0 w-full h-full border-0 rounded-md"
                        allowFullScreen
                        title="Content Review"
                        onError={(e) => {
                            console.error("Iframe load error:", e);
                            setMessage("Error loading document. Please check the Google Drive ID and ensure it's a publicly viewable embed link.");
                        }}
                    ></iframe>
                </div>
                <p className="text-gray-500 text-sm mt-2 text-center">
                    (This is an embedded Google Drive document viewer. Ensure your document is shared as "anyone with the link can view".)
                </p>
            </div>

            <div className="flex justify-center mb-8">
                {docStatus === 'Pending Review' ? (
                    <button
                        onClick={handleApprove}
                        className="inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200 ease-in-out transform hover:scale-105"
                    >
                        Approve Document
                    </button>
                ) : (
                    <p className="text-lg font-semibold text-green-700 bg-green-50 p-3 rounded-md">
                        Document has been Approved!
                    </p>
                )}
            </div>

            <div className="mb-8">
                <h2 className="text-2xl font-bold text-gray-800 mb-3">Leave a Comment</h2>
                <form onSubmit={handleCommentSubmit} className="space-y-4">
                    <div>
                        <label htmlFor="clientName" className="block text-sm font-medium text-gray-700">Your Name (Optional)</label>
                        <input
                            type="text"
                            id="clientName"
                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            value={clientName}
                            onChange={(e) => setClientName(e.target.value)}
                            placeholder="e.g., John Doe"
                        />
                    </div>
                    <div>
                        <label htmlFor="clientComment" className="block text-sm font-medium text-gray-700">Your Comment</label>
                        <textarea
                            id="clientComment"
                            rows="4"
                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            value={clientComment}
                            onChange={(e) => setClientComment(e.target.value)}
                            placeholder="Type your feedback here..."
                            required
                        ></textarea>
                    </div>
                    <button
                        type="submit"
                        className="w-full inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200 ease-in-out transform hover:scale-105"
                    >
                        Submit Comment
                    </button>
                </form>
            </div>

            <div>
                <h2 className="text-2xl font-bold text-gray-800 mb-3">Comments History</h2>
                {comments.length === 0 ? (
                    <p className="text-gray-600">No comments yet. Be the first to leave one!</p>
                ) : (
                    <div className="space-y-4">
                        {comments.map((comment) => (
                            <div key={comment.id} className="bg-gray-50 p-4 rounded-md shadow-sm border border-gray-200">
                                <p className="text-sm font-semibold text-indigo-600">
                                    {comment.name}
                                    {comment.timestamp && (
                                        <span className="text-gray-400 font-normal ml-2">
                      {(comment.timestamp.toDate && comment.timestamp.toDate().toLocaleString()) || 'Just now'}
                    </span>
                                    )}
                                </p>
                                <p className="mt-1 text-gray-800">{comment.commentText}</p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

// --- Dashboard Component ---
function Dashboard({ db, isAuthReady, magicToken, onSelectDocument, setMessage }) {
    const [documents, setDocuments] = useState([]);
    const [newDocTitle, setNewDocTitle] = useState('');
    const [newDocGoogleDriveId, setNewDocGoogleDriveId] = useState('');
    const [addingDocMessage, setAddingDocMessage] = useState('');
    const [selectedDocIds, setSelectedDocIds] = useState(new Set()); // New state for selected document IDs

    // Fetch documents for the specific magicTokenGroup
    useEffect(() => {
        if (db && isAuthReady && magicToken) {
            const documentsCollectionRef = collection(db, `artifacts/${appId}/public/data/clientPortalDocuments`);
            // Query based on magicTokenGroup, which will be 'DEMO_CLIENT_TOKEN' if not provided in URL
            const q = query(documentsCollectionRef, where('magicTokenGroup', '==', magicToken));

            const unsubscribe = onSnapshot(q, (snapshot) => {
                const fetchedDocs = [];
                snapshot.forEach((doc) => {
                    fetchedDocs.push({ id: doc.id, ...doc.data() });
                });
                // Sort by creation date, newest first
                fetchedDocs.sort((a, b) => (b.createdAt?.toDate() || 0) - (a.createdAt?.toDate() || 0));
                setDocuments(fetchedDocs);
                setMessage(''); // Clear any previous messages
            }, (error) => {
                console.error("Error fetching documents:", error);
                setMessage(`Error fetching documents: ${error.message}`);
            });

            return () => unsubscribe();
        }
    }, [db, isAuthReady, magicToken, setMessage]);

    const handleAddDocument = async (e) => {
        e.preventDefault();
        if (!newDocTitle.trim() || !newDocGoogleDriveId.trim()) {
            setAddingDocMessage('Please enter both title and Google Drive ID.');
            return;
        }
        if (!isAuthReady || !db || !magicToken) { // magicToken will always be available now due to default
            setAddingDocMessage('Application not ready or magic token missing.');
            return;
        }

        // Extract the Google Drive ID from the potential full URL
        let extractedGoogleDriveId = newDocGoogleDriveId.trim();
        // Regex to match Google Drive file ID from various shareable URL formats
        const driveIdMatch = extractedGoogleDriveId.match(/\/d\/([a-zA-Z0-9_-]+)(?:[\/?]|$)/);
        if (driveIdMatch && driveIdMatch[1]) {
            extractedGoogleDriveId = driveIdMatch[1];
        } else if (extractedGoogleDriveId.length < 20 && extractedGoogleDriveId.length > 5) {
            // Simple heuristic: if it's a short string (but not too short), assume it's already just the ID.
            // A more robust solution would involve validating the ID format precisely.
        } else {
            setAddingDocMessage('Invalid Google Drive ID or URL format. Please provide the document ID or a valid shareable link.');
            return;
        }

        try {
            const documentsCollectionRef = collection(db, `artifacts/${appId}/public/data/clientPortalDocuments`);
            await addDoc(documentsCollectionRef, {
                title: newDocTitle.trim(),
                googleDriveId: extractedGoogleDriveId, // Use the extracted ID
                status: 'Pending Review', // Default status for new documents
                magicTokenGroup: magicToken, // Associate with the current magic token
                createdAt: serverTimestamp(),
                lastUpdatedAt: serverTimestamp(),
            });
            setAddingDocMessage('Document added successfully!');
            setNewDocTitle('');
            setNewDocGoogleDriveId('');
        } catch (error) {
            console.error("Error adding document:", error);
            setAddingDocMessage(`Error adding document: ${error.message}`);
        }
    };

    // Handle individual checkbox change
    const handleCheckboxChange = (docId) => {
        setSelectedDocIds((prevSelected) => {
            const newSelected = new Set(prevSelected);
            if (newSelected.has(docId)) {
                newSelected.delete(docId);
            } else {
                newSelected.add(docId);
            }
            return newSelected;
        });
    };

    // Handle bulk approval
    const handleBulkApprove = async () => {
        if (selectedDocIds.size === 0) {
            setMessage('No documents selected for approval.');
            return;
        }
        if (!isAuthReady || !db) {
            setMessage('Application is not ready. Please wait.');
            return;
        }

        try {
            const approvePromises = [];
            selectedDocIds.forEach(docId => {
                // Find the full document object to check its current status
                const docToApprove = documents.find(d => d.id === docId);
                if (docToApprove && docToApprove.status === 'Pending Review') {
                    const docRef = doc(db, `artifacts/${appId}/public/data/clientPortalDocuments`, docId);
                    approvePromises.push(updateDoc(docRef, {
                        status: 'Approved',
                        lastUpdatedAt: serverTimestamp(),
                    }));
                }
            });

            if (approvePromises.length > 0) {
                await Promise.all(approvePromises);
                setMessage(`Successfully approved ${approvePromises.length} document(s)!`);
                setSelectedDocIds(new Set()); // Clear selection after approval
            } else {
                setMessage('Selected documents are already approved or not found.');
            }
        } catch (error) {
            console.error("Error during bulk approval:", error);
            setMessage(`Error during bulk approval: ${error.message}`);
        }
    };

    return (
        <div className="w-full max-w-4xl bg-white rounded-lg shadow-xl p-6 sm:p-8">
            <h1 className="text-3xl sm:text-4xl font-extrabold text-indigo-700 mb-4 text-center">
                Client Content Dashboard
            </h1>
            <p className="text-sm text-gray-500 mb-6 text-center">
                Magic Token Group: <span className="font-mono bg-gray-100 px-2 py-1 rounded">{magicToken || 'N/A'}</span>
                {/* Added a hint for the user that the token is now optional for testing */}
                <br/> <span className="text-xs text-gray-400">(Using a default token for testing if none provided in URL)</span>
            </p>

            {addingDocMessage && (
                <div className={`p-3 mb-4 rounded-md ${addingDocMessage.startsWith('Error') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} text-center`}>
                    {addingDocMessage}
                </div>
            )}

            {/* Add Document Form */}
            <div className="mb-10 p-6 bg-blue-50 rounded-lg border border-blue-200">
                <h2 className="text-2xl font-bold text-blue-800 mb-4">Add New Document for Review</h2>
                <form onSubmit={handleAddDocument} className="space-y-4">
                    <div>
                        <label htmlFor="newDocTitle" className="block text-sm font-medium text-gray-700">Document Title</label>
                        <input
                            type="text"
                            id="newDocTitle"
                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value={newDocTitle}
                            onChange={(e) => setNewDocTitle(e.target.value)}
                            placeholder="e.g., Q3 Blog Post: SEO Trends"
                            required
                        />
                    </div>
                    <div>
                        <label htmlFor="newDocGoogleDriveId" className="block text-sm font-medium text-gray-700">Google Drive File ID</label>
                        <input
                            type="text"
                            id="newDocGoogleDriveId"
                            className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value={newDocGoogleDriveId}
                            onChange={(e) => setNewDocGoogleDriveId(e.target.value)}
                            placeholder="e.g., 1s_lYq-2A5b9c0d1e2f3g4h5i6j7k8l9 (from Google Drive URL)"
                            required
                        />
                        <p className="text-xs text-gray-500 mt-1">
                            You can find this ID in the Google Drive shareable link: `drive.google.com/file/d/<span className="font-bold text-gray-700">YOUR_FILE_ID</span>/view`
                        </p>
                    </div>
                    <button
                        type="submit"
                        className="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200 ease-in-out transform hover:scale-105"
                    >
                        Add Document
                    </button>
                </form>
            </div>

            <div className="mb-8">
                <h2 className="text-2xl font-bold text-gray-800 mb-3">Documents Awaiting Your Attention</h2>

                {documents.length > 0 && selectedDocIds.size > 0 && (
                    <div className="flex justify-center mb-6">
                        <button
                            onClick={handleBulkApprove}
                            disabled={selectedDocIds.size === 0}
                            className="inline-flex items-center justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-200 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                            Approve Selected ({selectedDocIds.size})
                        </button>
                    </div>
                )}

                {documents.length === 0 ? (
                    <p className="text-gray-600">No documents found for this magic link group. Add one above!</p>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {documents.map((doc) => (
                            <div
                                key={doc.id}
                                className={`relative bg-gray-50 p-4 rounded-md shadow-sm border ${selectedDocIds.has(doc.id) ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200'} transition duration-150 ease-in-out`}
                            >
                                <div className="flex items-start justify-between">
                                    <h3 className="text-lg font-semibold text-indigo-600 truncate mr-2">{doc.title}</h3>
                                    <input
                                        type="checkbox"
                                        className="form-checkbox h-5 w-5 text-indigo-600 rounded-md focus:ring-indigo-500 mt-1"
                                        checked={selectedDocIds.has(doc.id)}
                                        onChange={() => handleCheckboxChange(doc.id)}
                                    />
                                </div>
                                <p className="text-sm text-gray-500 mt-1">Status: <span className={`font-medium ${doc.status === 'Pending Review' ? 'text-orange-500' : 'text-green-600'}`}>{doc.status}</span></p>
                                <p className="text-xs text-gray-400 mt-1">Added: {doc.createdAt?.toDate().toLocaleDateString() || 'N/A'}</p>
                                <button
                                    onClick={() => onSelectDocument(doc)}
                                    className="mt-3 text-indigo-500 hover:text-indigo-700 text-sm font-medium"
                                >
                                    Review & Comment
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

// --- Main App Component (Router) ---
export default function App() {
    const [db, setDb] = useState(null);
    const [auth, setAuth] = useState(null);
    const [userId, setUserId] = useState(null);
    const [isAuthReady, setIsAuthReady] = useState(false);

    const [magicToken, setMagicToken] = useState('');
    const [currentPage, setCurrentPage] = useState('dashboard'); // 'dashboard' or 'document'
    const [selectedDocument, setSelectedDocument] = useState(null);
    const [message, setMessage] = useState('');
    const [isLoading, setIsLoading] = useState(true);

    // Initialize Firebase and handle authentication
    useEffect(() => {
        try {
            const app = initializeApp(firebaseConfig);
            const firestore = getFirestore(app);
            const firebaseAuth = getAuth(app);

            setDb(firestore);
            setAuth(firebaseAuth);

            const unsubscribe = onAuthStateChanged(firebaseAuth, async (user) => {
                if (user) {
                    setUserId(user.uid);
                    setIsAuthReady(true);
                } else {
                    try {
                        if (!initialAuthToken) {
                            await signInAnonymously(firebaseAuth);
                        } else {
                            await signInWithCustomToken(firebaseAuth, initialAuthToken);
                        }
                    } catch (error) {
                        console.error("Error signing in to Firebase:", error);
                        setMessage(`Error: Could not authenticate. ${error.message}`);
                    }
                }
                setIsLoading(false);
            });

            return () => unsubscribe();
        } catch (error) {
            console.error("Firebase initialization failed:", error);
            setMessage(`Error: Firebase initialization failed. ${error.message}`);
            setIsLoading(false);
        }
    }, []);

    // Parse URL parameters for initial page and document selection
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('token');
        const page = params.get('page');
        const docId = params.get('documentId');

        // If no token is provided in the URL, use a default for testing
        if (token) {
            setMagicToken(token);
        } else {
            setMagicToken('DEMO_CLIENT_TOKEN'); // Set a default token for testing
        }

        if (page === 'document' && docId) { // No longer require magicToken for deep link here, as it will default
            setCurrentPage('document');
            setSelectedDocument({ googleDriveId: docId });
        } else {
            setCurrentPage('dashboard');
        }
    }, []);


    const handleSelectDocument = (doc) => {
        setSelectedDocument(doc);
        setCurrentPage('document');
        // Update URL without full page reload for better UX
        // Ensure the path always starts with '/', which is reliable in this environment
        window.history.pushState({}, '', `/?token=${magicToken}&page=document&documentId=${doc.googleDriveId}`);
    };

    const handleBackToDashboard = () => {
        setSelectedDocument(null);
        setCurrentPage('dashboard');
        // Update URL
        // Ensure the path always starts with '/', which is reliable in this environment
        window.history.pushState({}, '', `/?token=${magicToken}`);
        setMessage(''); // Clear messages when going back
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-gray-100 p-4">
                <div className="text-xl font-semibold text-gray-700">Loading Content Portal...</div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-100 flex flex-col items-center p-4 sm:p-6 lg:p-8">
            <p className="text-sm text-gray-500 mb-6 text-center">
                Your User ID (for app functions): <span className="font-mono bg-gray-100 px-2 py-1 rounded break-all">{userId || 'N/A'}</span>
            </p>
            {message && (
                <div className={`p-3 mb-4 rounded-md ${message.startsWith('Error') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'} text-center w-full max-w-4xl`}>
                    {message}
                </div>
            )}

            {currentPage === 'dashboard' && (
                <Dashboard
                    db={db}
                    isAuthReady={isAuthReady}
                    magicToken={magicToken}
                    onSelectDocument={handleSelectDocument}
                    setMessage={setMessage}
                />
            )}

            {currentPage === 'document' && selectedDocument && (
                <DocumentDetail
                    db={db}
                    isAuthReady={isAuthReady}
                    currentDocument={selectedDocument}
                    magicToken={magicToken}
                    onBackToDashboard={handleBackToDashboard}
                />
            )}
            {/* Tailwind CSS CDN script */}
            <script src="https://cdn.tailwindcss.com"></script>
        </div>
    );
}
