import React, { useState, useEffect } from 'react';
import { useSprings, animated, useSpring } from 'react-spring';

// All users start with 0 points. The winner will be the person with the highest finalPoints.
const rawLeaderboardData = [
    { id: 'user-2', name: 'Maryam', points: 0, finalPoints: 2000, userType: 'Contractor' },
    { id: 'user-3', name: 'Sakhi Ghulam', points: 0, finalPoints: 3000, userType: 'Employee' },
    { id: 'user-4', name: 'Usama Saeed', points: 0, finalPoints: 2100, userType: 'Employee' },
    { id: 'user-5', name: 'Ahmad Khan', points: 0, finalPoints: 1850, userType: 'Contractor' },
    { id: 'user-6', name: 'Sara Ali', points: 0, finalPoints: 1500, userType: 'Employee' },
    { id: 'user-7', name: 'Bilal Riaz', points: 0, finalPoints: 1300, userType: 'Contractor' },
    { id: 'user-8', name: 'Farah Ahmed', points: 0, finalPoints: 1050, userType: 'Employee' },
    { id: 'user-9', name: 'Imran Malik', points: 0, finalPoints: 900, userType: 'Contractor' },
    { id: 'user-10', name: 'Sana Khan', points: 0, finalPoints: 100, userType: 'Employee' },
    { id: 'user-1', name: 'Zeeshan Sabri', points: 0, finalPoints: 3500, userType: 'Employee' },
];

const App = () => {
    // Static USER_ID for which to display a personalized message
    const USER_ID = 'user-2';
    const CARD_HEIGHT = 80;
    const CARD_MARGIN = 8;
    const ITEM_HEIGHT = CARD_HEIGHT + CARD_MARGIN;

    // Determine the overall winner based on the highest finalPoints
    const winner = rawLeaderboardData.reduce((prev, current) =>
        (prev.finalPoints > current.finalPoints) ? prev : current
    );

    const [leaderboard, setLeaderboard] = useState(() => {
        const winnerData = rawLeaderboardData.find(user => user.id === winner.id);
        const others = rawLeaderboardData.filter(user => user.id !== winner.id);
        const shuffledOthers = others.sort(() => Math.random() - 0.5);
        const initialOrder = [...shuffledOthers, winnerData];
        return initialOrder.map((user, index) => ({
            ...user,
            rank: index + 1,
        }));
    });

    const [congratsState, setCongratsState] = useState({
        show: false,
        message: { title: '', text: '' }
    });

    const maxFinalPoints = rawLeaderboardData.reduce((max, user) => Math.max(max, user.finalPoints), 0);
    const maxIncrement = maxFinalPoints > 1000 ? 100 : (maxFinalPoints > 200 ? 25 : 10);

    const congratsSpring = useSpring({
        opacity: congratsState.show ? 1 : 0,
        transform: `scale(${congratsState.show ? 1 : 0.8})`,
        config: { duration: 500 },
    });

    const handleCloseCongrats = () => {
        setCongratsState({
            show: false,
            message: { title: '', text: '' }
        });
    };

    useEffect(() => {
        const interval = setInterval(() => {
            setLeaderboard(currentLeaderboard => {
                const newLeaderboard = [...currentLeaderboard];
                let allScoresFinal = true;

                newLeaderboard.forEach(user => {
                    if (user.points < user.finalPoints) {
                        const pointsToAdd = Math.floor(Math.random() * maxIncrement) + 1;
                        user.points = Math.min(user.points + pointsToAdd, user.finalPoints);
                        allScoresFinal = false;
                    }
                });

                const sortedLeaderboard = newLeaderboard.sort((a, b) => b.points - a.points);
                const rankedLeaderboard = sortedLeaderboard.map((user, index) => ({
                    ...user,
                    rank: index + 1,
                }));

                if (allScoresFinal) {
                    clearInterval(interval);
                    const userToTrack = rankedLeaderboard.find(user => user.id === USER_ID);
                    if (userToTrack) {
                        if (userToTrack.rank === 1) {
                            setCongratsState({
                                show: true,
                                message: { title: '🎉 Congratulations!', text: `${userToTrack.name}, you are the winner!` }
                            });
                        } else if (userToTrack.rank <= 3) {
                            setCongratsState({
                                show: true,
                                message: { title: '🏆 Well done!', text: `${userToTrack.name}, you made it to the top 3!` }
                            });
                        } else {
                            setCongratsState({
                                show: true,
                                message: { title: '🏁 Final Standings!', text: `Hey ${userToTrack.name}, you finished at rank ${userToTrack.rank}! Keep submitting your standups on time and completing tasks to climb the ranks next month. You're doing great!` }
                            });
                        }
                    }
                }
                return rankedLeaderboard;
            });
        }, 200);

        return () => clearInterval(interval);
    }, []);

    // Use the initial order to map springs, but animate to the final rank's position
    const springs = useSprings(
        leaderboard.length,
        rawLeaderboardData.map((user, index) => {
            // Find the user's final rank from the `leaderboard` state
            const finalRank = leaderboard.find(u => u.id === user.id)?.rank || (index + 1);
            return {
                y: (finalRank - 1) * ITEM_HEIGHT,
                points: leaderboard.find(u => u.id === user.id)?.points || 0,
                config: { tension: 150, friction: 50, duration: 1000 }
            };
        })
    );

    const getRankClasses = (user) => {
        const isWinner = user.id === winner.id;
        const isTrackedUser = user.id === USER_ID;

        let classes = `relative flex items-center justify-between p-4 sm:p-6 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-xl my-1`;

        if (isWinner) {
            classes += ' bg-gradient-to-r from-yellow-500 to-orange-500 text-white z-20';
        } else if (isTrackedUser) {
            classes += ' bg-gray-800 border-2 border-cyan-400 text-white z-10';
        } else {
            switch (user.rank) {
                case 1:
                    classes += ' bg-gray-800 border-2 border-yellow-400 text-white z-10';
                    break;
                case 2:
                    classes += ' bg-gray-800 border-2 border-gray-400 text-white';
                    break;
                case 3:
                    classes += ' bg-gray-800 border-2 border-orange-400 text-white';
                    break;
                default:
                    classes += ' bg-gray-800 border border-gray-700 text-white';
                    break;
            }
        }
        return classes;
    };

    const getRankTextColor = (user) => {
        const isWinner = user.id === winner.id;
        const isTrackedUser = user.id === USER_ID;

        if (isWinner) {
            return 'text-white';
        } else if (isTrackedUser) {
            return 'text-cyan-400';
        }

        switch (user.rank) {
            case 1:
                return 'text-yellow-400 animate-glow';
            case 2:
                return 'text-gray-400';
            case 3:
                return 'text-orange-400';
            default:
                return 'text-gray-500';
        }
    };

    const getPointTextColor = (user) => {
        const isWinner = user.id === winner.id;
        return isWinner ? 'text-white' : 'text-green-400';
    };

    return (
        <div className="min-h-screen bg-gray-950 text-white flex flex-col items-center p-4 sm:p-8 font-inter overflow-hidden">
            <script src="https://cdn.tailwindcss.com"></script>
            <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
        .font-inter { font-family: 'Inter', sans-serif; }
        @keyframes pulse-glow {
          0%, 100% { text-shadow: 0 0 5px #fde047; }
          50% { text-shadow: 0 0 15px #fde047, 0 0 25px #fde047; }
        }
        .animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
        .congrats-overlay { background: rgba(0,0,0,0.7); }
      `}</style>

            {/* Main Title with Gradient and Pulse */}
            <h1 className="text-5xl sm:text-6xl font-extrabold text-center mb-10 text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 animate-pulse-glow">
                Monthly Leaderboard
            </h1>

            {/* Congratulations Modal Overlay */}
            {congratsState.show && (
                <animated.div
                    className="congrats-overlay fixed inset-0 flex items-center justify-center z-50 p-4"
                    style={{ opacity: congratsSpring.opacity }}
                >
                    <animated.div
                        className="congratulations-card bg-gray-800 p-8 sm:p-12 rounded-3xl shadow-2xl text-white text-center border-4 border-yellow-400 transform scale-100 relative"
                        style={congratsSpring}
                    >
                        <button
                            onClick={handleCloseCongrats}
                            className="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <h2 className="text-4xl sm:text-5xl font-extrabold mb-4">{congratsState.message.title}</h2>
                        <p className="text-xl sm:text-2xl font-semibold">{congratsState.message.text}</p>
                    </animated.div>
                </animated.div>
            )}

            {/* Leaderboard container with dynamic height */}
            <div className="w-full max-w-2xl relative" style={{ height: `${rawLeaderboardData.length * ITEM_HEIGHT}px` }}>
                {springs.map((props, index) => {
                    // Use the `rawLeaderboardData` to find the initial user,
                    // then get the animated state from `props`
                    const user = rawLeaderboardData[index];
                    const animatedUser = leaderboard.find(u => u.id === user.id);
                    const isWinner = animatedUser.id === winner.id;

                    return (
                        <animated.div
                            key={user.id}
                            style={{
                                position: 'absolute',
                                width: '100%',
                                transform: props.y.to(y => `translate3d(0, ${y}px, 0)`),
                                zIndex: isWinner ? 20 : (leaderboard.length - animatedUser.rank),
                            }}
                            className={getRankClasses(animatedUser)}
                        >
                            <div className="flex items-center space-x-4">
                <span className={`font-extrabold text-2xl sm:text-3xl ${getRankTextColor(animatedUser)}`}>
                  {animatedUser.rank}
                    {animatedUser.rank === 1 && !isWinner && (
                        <span className="ml-2 text-yellow-400">🏆</span>
                    )}
                </span>
                                <div className="flex items-center space-x-2">
                                    {animatedUser.userType === 'Employee' ? (
                                        <span className="text-2xl">💼</span>
                                    ) : (
                                        <span className="text-2xl">👷</span>
                                    )}
                                    <span className="text-lg sm:text-xl font-semibold">{animatedUser.name}</span>
                                </div>
                            </div>
                            <div className="flex items-center space-x-2">
                                <animated.span className={`text-xl sm:text-2xl font-bold ${getPointTextColor(animatedUser)}`}>
                                    {props.points.to(p => Math.round(p))}
                                </animated.span>
                                <span className={`text-sm ${isWinner ? 'text-white' : 'text-gray-400'}`}>pts</span>
                            </div>
                        </animated.div>
                    );
                })}
            </div>
        </div>
    );
};

export default App;
