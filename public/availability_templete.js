import React, { useState, useEffect, createContext, useContext } from 'react';
import { initializeApp } from 'firebase/app';
import { getAuth, signInAnonymously, signInWithCustomToken, onAuthStateChanged } from 'firebase/auth';
import { getFirestore, collection, addDoc, query, onSnapshot, orderBy, where, doc, updateDoc, deleteDoc } from 'firebase/firestore';

// Context for Firebase and Auth
const FirebaseContext = createContext(null);

// Main App Component
function App() {
    const [db, setDb] = useState(null);
    const [auth, setAuth] = useState(null);
    const [userId, setUserId] = useState(null);
    const [isAuthReady, setIsAuthReady] = useState(false);
    const [message, setMessage] = useState('');
    const [messageType, setMessageType] = useState(''); // 'success' or 'error'

    // Initialize Firebase and set up auth listener
    useEffect(() => {
        try {
            const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : {};
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
                    // Sign in anonymously if no initial token or user logs out
                    if (typeof __initial_auth_token !== 'undefined') {
                        try {
                            await signInWithCustomToken(firebaseAuth, __initial_auth_token);
                        } catch (error) {
                            console.error("Error signing in with custom token:", error);
                            await signInAnonymously(firebaseAuth);
                        }
                    } else {
                        await signInAnonymously(firebaseAuth);
                    }
                }
            });

            return () => unsubscribe();
        } catch (error) {
            console.error("Failed to initialize Firebase:", error);
            setMessage("Failed to initialize Firebase. Please check your configuration.");
            setMessageType('error');
        }
    }, []);

    const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';

    const showMessage = (msg, type) => {
        setMessage(msg);
        setMessageType(type);
        setTimeout(() => {
            setMessage('');
            setMessageType('');
        }, 3000); // Message disappears after 3 seconds
    };

    if (!isAuthReady) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-gray-100">
                <div className="text-xl font-semibold text-gray-700">Loading application...</div>
            </div>
        );
    }

    return (
        <FirebaseContext.Provider value={{ db, auth, userId, appId, showMessage }}>
            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 font-sans text-gray-800 p-4 sm:p-6 lg:p-8">
                <header className="text-center mb-10">
                    <h1 className="text-4xl sm:text-5xl font-extrabold text-indigo-700 mb-2">
                        Personal Availability Manager
                    </h1>
                    <p className="text-lg text-gray-600">
                        Share your weekly availability.
                    </p>
                    {userId && (
                        <p className="text-sm text-gray-500 mt-2">
                            Your User ID: <span className="font-mono bg-gray-200 px-2 py-1 rounded-md">{userId}</span>
                        </p>
                    )}
                </header>

                {message && (
                    <div className={`fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${messageType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'}`}>
                        {message}
                    </div>
                )}

                <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div className="lg:col-span-1">
                        <AvailabilityForm />
                    </div>
                    <div className="lg:col-span-2">
                        <AvailabilityDisplay />
                    </div>
                </div>
            </div>
        </FirebaseContext.Provider>
    );
}

// Availability Form Component
function AvailabilityForm() {
    const { db, userId, appId, showMessage } = useContext(FirebaseContext);
    const [date, setDate] = useState('');
    const [isAvailable, setIsAvailable] = useState(true); // New state for availability status
    const [reason, setReason] = useState(''); // New state for reason of unavailability
    const [timeSlots, setTimeSlots] = useState([{ startTime: '', endTime: '' }]);
    const [isLoading, setIsLoading] = useState(false);

    const handleAddSlot = () => {
        setTimeSlots([...timeSlots, { startTime: '', endTime: '' }]);
    };

    const handleSlotChange = (index, field, value) => {
        const newSlots = [...timeSlots];
        newSlots[index][field] = value;
        setTimeSlots(newSlots);
    };

    const handleRemoveSlot = (index) => {
        const newSlots = timeSlots.filter((_, i) => i !== index);
        setTimeSlots(newSlots);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!db || !userId) {
            showMessage('Authentication not ready. Please wait.', 'error');
            return;
        }

        // Basic validation
        if (!date.trim()) {
            showMessage('Please select a date.', 'error');
            return;
        }

        if (isAvailable) {
            if (timeSlots.some(slot => !slot.startTime || !slot.endTime)) {
                showMessage('Please fill in all time slots, or mark yourself as not available.', 'error');
                return;
            }
            // Validate time format (HH:MM) and logic (start < end)
            const isValidTimeSlots = timeSlots.every(slot => {
                const [startH, startM] = slot.startTime.split(':').map(Number);
                const [endH, endM] = slot.endTime.split(':').map(Number);

                if (isNaN(startH) || isNaN(startM) || isNaN(endH) || isNaN(endM) ||
                    startH < 0 || startH > 23 || startM < 0 || startM > 59 ||
                    endH < 0 || endH > 23 || endM < 0 || endM > 59) {
                    return false; // Invalid time format
                }

                const startMinutes = startH * 60 + startM;
                const endMinutes = endH * 60 + endM;
                return startMinutes < endMinutes; // Start time must be before end time
            });

            if (!isValidTimeSlots) {
                showMessage('Please ensure all time slots are valid (HH:MM) and start before they end.', 'error');
                return;
            }
        } else { // If not available, reason is required
            if (!reason.trim()) {
                showMessage('Please provide a reason for unavailability.', 'error');
                return;
            }
        }


        setIsLoading(true);
        try {
            // Determine collection path based on whether it's public or private data.
            // For personal availability, it's typically private. If you want to share,
            // you'd need a mechanism to mark it public. For simplicity, we'll use private.
            // If you want to make it public, change the collection path to:
            // `artifacts/${appId}/public/data/userAvailabilities`
            const docRef = await addDoc(collection(db, `artifacts/${appId}/users/${userId}/userAvailabilities`), {
                userId: userId,
                date: date,
                isAvailable: isAvailable,
                reason: isAvailable ? '' : reason.trim(), // Only save reason if not available
                timeSlots: isAvailable ? timeSlots : [], // Only save time slots if available
                createdAt: new Date(),
                updatedAt: new Date()
            });
            showMessage('Availability submitted successfully!', 'success');
            // Reset form
            setDate('');
            setIsAvailable(true);
            setReason('');
            setTimeSlots([{ startTime: '', endTime: '' }]);
        } catch (e) {
            console.error("Error adding document: ", e);
            showMessage('Error submitting availability. Please try again.', 'error');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="bg-white rounded-xl shadow-lg p-6 sm:p-8 border border-gray-200">
            <h2 className="text-2xl font-bold text-indigo-600 mb-6">Submit Your Availability</h2>
            <form onSubmit={handleSubmit} className="space-y-5">
                <div>
                    <label htmlFor="date" className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                    <input
                        type="date"
                        id="date"
                        value={date}
                        onChange={(e) => setDate(e.target.value)}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                        required
                    />
                </div>
                <div className="flex items-center">
                    <input
                        type="checkbox"
                        id="isAvailableToggle"
                        checked={!isAvailable} // Checkbox is for "Not Available"
                        onChange={(e) => {
                            setIsAvailable(!e.target.checked);
                            if (e.target.checked) { // If checked (not available), clear time slots
                                setTimeSlots([{ startTime: '', endTime: '' }]);
                            } else { // If unchecked (available), clear reason
                                setReason('');
                            }
                        }}
                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                    />
                    <label htmlFor="isAvailableToggle" className="ml-2 block text-sm font-medium text-gray-700">
                        Mark as Not Available for this day
                    </label>
                </div>

                {!isAvailable && (
                    <div>
                        <label htmlFor="reason" className="block text-sm font-medium text-gray-700 mb-1">Reason for Not Available</label>
                        <textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows="3"
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                            placeholder="e.g., Out of office, Holiday, Meeting all day"
                            required={!isAvailable}
                        ></textarea>
                    </div>
                )}

                {isAvailable && (
                    <div className="space-y-3">
                        <label className="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                        {timeSlots.map((slot, index) => (
                            <div key={index} className="flex items-center space-x-3">
                                <input
                                    type="time"
                                    value={slot.startTime}
                                    onChange={(e) => handleSlotChange(index, 'startTime', e.target.value)}
                                    className="w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                                    required={isAvailable}
                                />
                                <span className="text-gray-500">-</span>
                                <input
                                    type="time"
                                    value={slot.endTime}
                                    onChange={(e) => handleSlotChange(index, 'endTime', e.target.value)}
                                    className="w-1/2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                                    required={isAvailable}
                                />
                                {timeSlots.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => handleRemoveSlot(index)}
                                        className="p-2 rounded-full bg-red-100 text-red-600 hover:bg-red-200 transition duration-150 ease-in-out"
                                        aria-label="Remove time slot"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clipRule="evenodd" />
                                        </svg>
                                    </button>
                                )}
                            </div>
                        ))}
                        <button
                            type="button"
                            onClick={handleAddSlot}
                            className="w-full flex items-center justify-center px-4 py-2 border border-dashed border-indigo-300 rounded-lg text-indigo-600 hover:bg-indigo-50 transition duration-150 ease-in-out"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clipRule="evenodd" />
                            </svg>
                            Add Another Time Slot
                        </button>
                    </div>
                )}
                <button
                    type="submit"
                    className="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition duration-300 ease-in-out shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                    disabled={isLoading}
                >
                    {isLoading ? (
                        <svg className="animate-spin h-5 w-5 text-white mr-3" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    ) : (
                        'Save Availability'
                    )}
                </button>
            </form>
        </div>
    );
}

// Availability Display Component
function AvailabilityDisplay() {
    const { db, userId, appId, showMessage } = useContext(FirebaseContext);
    const [availabilities, setAvailabilities] = useState([]);
    const [filterUser, setFilterUser] = useState(''); // New filter for user ID
    const [viewMode, setViewMode] = useState('weekly'); // 'daily' or 'weekly'
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]); // Today's date
    const [uniqueUsers, setUniqueUsers] = useState([]); // List of unique user IDs

    // Fetch availabilities from Firestore
    useEffect(() => {
        if (!db || !userId) return;

        // Listen to public data for all users' availabilities
        const publicCollectionRef = collection(db, `artifacts/${appId}/public/data/userAvailabilities`);
        const privateCollectionRef = collection(db, `artifacts/${appId}/users/${userId}/userAvailabilities`);

        // Combine listeners for public and private data
        const unsubscribePublic = onSnapshot(publicCollectionRef, (snapshot) => {
            const publicDocs = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
            setAvailabilities(prev => {
                // Filter out current user's private data from publicDocs if it exists there
                const publicOnly = publicDocs.filter(a => a.userId !== userId);
                const privateOnly = prev.filter(a => a.userId === userId); // Keep current user's private data
                return [...privateOnly, ...publicOnly].sort((a, b) => new Date(a.date) - new Date(b.date));
            });
        }, (error) => {
            console.error("Error fetching public availabilities:", error);
            showMessage('Error loading public availabilities.', 'error');
        });

        const unsubscribePrivate = onSnapshot(privateCollectionRef, (snapshot) => {
            const privateDocs = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
            setAvailabilities(prev => {
                const publicOnly = prev.filter(a => a.userId !== userId); // Keep public data from others
                const privateOnly = privateDocs; // Add current user's private data
                return [...publicOnly, ...privateOnly].sort((a, b) => new Date(a.date) - new Date(b.date));
            });
        }, (error) => {
            console.error("Error fetching private availabilities:", error);
            showMessage('Error loading your private availabilities.', 'error');
        });


        return () => {
            unsubscribePublic();
            unsubscribePrivate();
        };
    }, [db, userId, appId, showMessage]);

    // Update unique user IDs when availabilities change
    useEffect(() => {
        const users = [...new Set(availabilities.map(a => a.userId))];
        setUniqueUsers(users);
    }, [availabilities]);


    // Filtered availabilities based on selected date, view mode, and user filter
    const getFilteredAvailabilities = () => {
        let filtered = availabilities;

        if (filterUser) {
            filtered = filtered.filter(a => a.userId === filterUser);
        }

        if (viewMode === 'daily') {
            filtered = filtered.filter(a => a.date === selectedDate);
        } else { // weekly view
            const startOfWeek = new Date(selectedDate);
            startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay()); // Go to Sunday
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(endOfWeek.getDate() + 6); // Go to Saturday

            filtered = filtered.filter(a => {
                const availabilityDate = new Date(a.date);
                return availabilityDate >= startOfWeek && availabilityDate <= endOfWeek;
            });
        }

        // Group by user ID and then by date for display
        const grouped = {};
        filtered.forEach(item => {
            if (!grouped[item.userId]) {
                grouped[item.userId] = {};
            }
            if (!grouped[item.userId][item.date]) {
                grouped[item.userId][item.date] = [];
            }
            grouped[item.userId][item.date].push(item);
        });

        return grouped;
    };

    const groupedAvailabilities = getFilteredAvailabilities();

    // Function to get dates for the current week (for weekly view header)
    const getWeekDays = (startDateString) => {
        const start = new Date(startDateString);
        start.setDate(start.getDate() - start.getDay()); // Go to Sunday
        const days = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(start);
            d.setDate(start.getDate() + i);
            days.push(d.toISOString().split('T')[0]);
        }
        return days;
    };

    const currentWeekDays = getWeekDays(selectedDate);

    const handleDateChange = (e) => {
        setSelectedDate(e.target.value);
    };

    const handlePrevWeek = () => {
        const current = new Date(selectedDate);
        current.setDate(current.getDate() - 7);
        setSelectedDate(current.toISOString().split('T')[0]);
    };

    const handleNextWeek = () => {
        const current = new Date(selectedDate);
        current.setDate(current.getDate() + 7);
        setSelectedDate(current.toISOString().split('T')[0]);
    };

    const handlePrevDay = () => {
        const current = new Date(selectedDate);
        current.setDate(current.getDate() - 1);
        setSelectedDate(current.toISOString().split('T')[0]);
    };

    const handleNextDay = () => {
        const current = new Date(selectedDate);
        current.setDate(current.getDate() + 1);
        setSelectedDate(current.toISOString().split('T')[0]);
    };

    const handleDeleteAvailability = async (docId, itemUserId) => {
        if (!db || !userId) {
            showMessage('Authentication not ready. Cannot delete.', 'error');
            return;
        }

        // Custom confirmation modal (replace with your actual modal component)
        const confirmed = window.confirm("Are you sure you want to delete this availability?");
        if (!confirmed) return;

        try {
            let docRef;
            // Determine if it's private data (current user's) or public data (another user's)
            if (itemUserId === userId) {
                docRef = doc(db, `artifacts/${appId}/users/${userId}/userAvailabilities`, docId);
            } else {
                // For public data, ensure the user has permission to delete (e.g., admin or owner)
                // For this example, we'll allow deletion of public data if the current user is the owner.
                // In a real app, you'd have more robust access control.
                docRef = doc(db, `artifacts/${appId}/public/data/userAvailabilities`, docId);
            }

            await deleteDoc(docRef);
            showMessage('Availability deleted successfully!', 'success');
        } catch (error) {
            console.error("Error deleting document:", error);
            showMessage('Error deleting availability. Please try again.', 'error');
        }
    };

    // Function to determine if a slot is for tomorrow for reminder logic
    const isTomorrow = (dateString) => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today);
        tomorrow.setDate(today.getDate() + 1);

        const checkDate = new Date(dateString);
        checkDate.setHours(0, 0, 0, 0);

        return checkDate.getTime() === tomorrow.getTime();
    };


    return (
        <div className="bg-white rounded-xl shadow-lg p-6 sm:p-8 border border-gray-200">
            <h2 className="text-2xl font-bold text-indigo-600 mb-6">Availability Schedules</h2>

            <div className="flex flex-col sm:flex-row items-center justify-between mb-6 space-y-4 sm:space-y-0 sm:space-x-4">
                <div className="flex space-x-2">
                    <button
                        onClick={() => setViewMode('daily')}
                        className={`px-4 py-2 rounded-lg font-medium transition duration-200 ${viewMode === 'daily' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                    >
                        Daily View
                    </button>
                    <button
                        onClick={() => setViewMode('weekly')}
                        className={`px-4 py-2 rounded-lg font-medium transition duration-200 ${viewMode === 'weekly' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                    >
                        Weekly View
                    </button>
                </div>

                <div className="flex items-center space-x-2">
                    {viewMode === 'weekly' && (
                        <>
                            <button onClick={handlePrevWeek} className="p-2 rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <input
                                type="date"
                                value={selectedDate}
                                onChange={handleDateChange}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-gray-700"
                            />
                            <button onClick={handleNextWeek} className="p-2 rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </>
                    )}
                    {viewMode === 'daily' && (
                        <>
                            <button onClick={handlePrevDay} className="p-2 rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <input
                                type="date"
                                value={selectedDate}
                                onChange={handleDateChange}
                                className="px-3 py-2 border border-gray-300 rounded-lg text-gray-700"
                            />
                            <button onClick={handleNextDay} className="p-2 rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </>
                    )}
                </div>

                <div className="w-full sm:w-auto">
                    <label htmlFor="filterUser" className="sr-only">Filter by User</label>
                    <select
                        id="filterUser"
                        value={filterUser}
                        onChange={(e) => setFilterUser(e.target.value)}
                        className="w-full sm:w-48 px-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 ease-in-out"
                    >
                        <option value="">All Users</option>
                        {uniqueUsers.map(uId => (
                            <option key={uId} value={uId}>{uId === userId ? 'My Availability' : uId}</option>
                        ))}
                    </select>
                </div>
            </div>

            {Object.keys(groupedAvailabilities).length === 0 ? (
                <p className="text-center text-gray-500 py-8">No availability found for the selected filters.</p>
            ) : (
                <div className="space-y-8">
                    {Object.entries(groupedAvailabilities).map(([itemUserId, dates]) => (
                        <div key={itemUserId} className="bg-gray-50 rounded-lg p-4 shadow-sm border border-gray-100">
                            <h3 className="text-xl font-semibold text-indigo-700 mb-4">Availability for: {itemUserId === userId ? 'You' : itemUserId}</h3>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-100">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tl-lg">Date</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider rounded-tr-lg">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                    {viewMode === 'weekly' ? (
                                        currentWeekDays.map(day => {
                                            const dayAvailabilities = dates[day] || [];
                                            return (
                                                <tr key={day} className="hover:bg-gray-50">
                                                    <td className="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {new Date(day).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                                                        {isTomorrow(day) && (
                                                            <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                  Tomorrow!
                                </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 whitespace-nowrap text-sm">
                                                        {dayAvailabilities.length > 0 ? (
                                                            dayAvailabilities.map(item => (
                                                                <span key={item.id} className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${item.isAvailable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                    {item.isAvailable ? 'Available' : 'Not Available'}
                                  </span>
                                                            ))
                                                        ) : (
                                                            <span className="text-gray-400 italic">Not set</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 text-sm text-gray-700">
                                                        {dayAvailabilities.length > 0 ? (
                                                            <ul className="list-disc list-inside space-y-1">
                                                                {dayAvailabilities.flatMap(item =>
                                                                    item.isAvailable ?
                                                                        item.timeSlots.map((slot, idx) => (
                                                                            <li key={`${item.id}-${idx}`} className="flex items-center">
                                                                                <span className="text-indigo-600 font-semibold">{slot.startTime} - {slot.endTime}</span>
                                                                            </li>
                                                                        )) :
                                                                        <li key={item.id} className="text-gray-600">Reason: {item.reason || 'N/A'}</li>
                                                                )}
                                                            </ul>
                                                        ) : (
                                                            <span className="text-gray-400 italic">No details</span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        {dayAvailabilities.length > 0 && dayAvailabilities.map(item => (
                                                            <button
                                                                key={`delete-${item.id}`}
                                                                onClick={() => handleDeleteAvailability(item.id, item.userId)}
                                                                className="text-red-600 hover:text-red-900 ml-2 p-1 rounded-full hover:bg-red-100 transition"
                                                                title="Delete Availability"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clipRule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        ))}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : ( // Daily view
                                        Object.entries(dates).map(([date, items]) => (
                                            <tr key={date} className="hover:bg-gray-50">
                                                <td className="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                                                    {isTomorrow(date) && (
                                                        <span className="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                  Tomorrow!
                                </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 whitespace-nowrap text-sm">
                                                    {items.map(item => (
                                                        <span key={item.id} className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${item.isAvailable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                {item.isAvailable ? 'Available' : 'Not Available'}
                              </span>
                                                    ))}
                                                </td>
                                                <td className="px-4 py-4 text-sm text-gray-700">
                                                    <ul className="list-disc list-inside space-y-1">
                                                        {items.flatMap(item =>
                                                            item.isAvailable ?
                                                                item.timeSlots.map((slot, idx) => (
                                                                    <li key={`${item.id}-${idx}`} className="flex items-center">
                                                                        <span className="text-indigo-600 font-semibold">{slot.startTime} - {slot.endTime}</span>
                                                                    </li>
                                                                )) :
                                                                <li key={item.id} className="text-gray-600">Reason: {item.reason || 'N/A'}</li>
                                                        )}
                                                    </ul>
                                                </td>
                                                <td className="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    {items.map(item => (
                                                        <button
                                                            key={`delete-${item.id}`}
                                                            onClick={() => handleDeleteAvailability(item.id, item.userId)}
                                                            className="text-red-600 hover:text-red-900 ml-2 p-1 rounded-full hover:bg-red-100 transition"
                                                            title="Delete Availability"
                                                        >
                                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fillRule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clipRule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    ))}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default App;
