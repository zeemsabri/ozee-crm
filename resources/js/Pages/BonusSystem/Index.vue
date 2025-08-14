<script setup>
import { onMounted } from 'vue';
import {Head, Link} from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Chart from 'chart.js/auto';
import {createRouter as $router} from "vue-router";

onMounted(() => {
    const englishBtn = document.getElementById('english-btn');
    const urduBtn = document.getElementById('urdu-btn');
    const contentEnglish = document.getElementById('content-english');
    const contentUrdu = document.getElementById('content-urdu');

    // Chart data and labels for both languages
    const chartData = {
        projectTiers: {
            data: [1.5, 1.0, 0.75],
            labels: {
                en: ['Tier 1 (High Priority)', 'Tier 2 (Standard)', 'Tier 3 (Low Priority)'],
                ur: ['ٹیر 1 (اعلیٰ ترجیح)', 'ٹیر 2 (معیاری)', 'ٹیر 3 (کم ترجیح)']
            },
            tooltipTitles: {
                en: ['Tier 1 (High Priority Projects)', 'Tier 2 (Standard Projects)', 'Tier 3 (Low Priority Projects)'],
                ur: ['ٹیر 1 (اعلیٰ ترجیحی پراجیکٹس)', 'ٹیر 2 (معیاری پراجیکٹس)', 'ٹیر 3 (کم ترجیحی پراجیکٹس)']
            }
        },
        bonusTiers: {
            data: [1000, 1500, 2000],
            labels: {
                en: ['Bronze Tier', 'Silver Tier', 'Gold Tier'],
                ur: ['کانسی کا ٹیر', 'چاندی کا ٹیر', 'سونے کا ٹیر']
            },
            ranges: {
                en: ['1,000 - 1,499', '1,500 - 1,999', '2,000+'],
                ur: ['1,000 - 1,499', '1,500 - 1,999', '2,000+']
            }
        }
    };

    let projectTiersChartInstance;
    let bonusTiersChartInstance;

    // Utility: add reveal class to elements we want to animate on scroll
    const applyRevealClasses = () => {
        const selectors = ['section', '.card-bg', '.timeline-item', '.cta-card'];
        selectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => el.classList.add('reveal-on-scroll'));
        });
        // Also add to header title and paragraph
        document.querySelectorAll('header h1, header p').forEach(el => el.classList.add('reveal-on-scroll'));
    };

    // Utility: setup intersection observer for reveal-on-scroll
    const setupScrollReveal = () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal-on-scroll').forEach(el => observer.observe(el));
    };

    // Utility: quick fade animation on newly shown content containers
    const playFade = (el) => {
        if (!el) return;
        el.classList.add('anim-fade-in');
        setTimeout(() => el.classList.remove('anim-fade-in'), 700);
    };

    const wrapLabel = (label, max_width) => {
        if (label.length <= max_width) return label;
        const words = label.split(' ');
        const lines = [];
        let currentLine = '';
        words.forEach(word => {
            if ((currentLine + ' ' + word).length > max_width) {
                lines.push(currentLine);
                currentLine = word;
            } else {
                currentLine = currentLine ? currentLine + ' ' + word : word;
            }
        });
        lines.push(currentLine);
        return lines;
    };

    const createCharts = (lang) => {
        if (projectTiersChartInstance) projectTiersChartInstance.destroy();
        if (bonusTiersChartInstance) bonusTiersChartInstance.destroy();

        const projectTiersChartCtx = document.getElementById(lang === 'en' ? 'projectTiersChart' : 'projectTiersChartUrdu').getContext('2d');
        const bonusTiersChartCtx = document.getElementById(lang === 'en' ? 'bonusTiersChart' : 'bonusTiersChartUrdu').getContext('2d');

        projectTiersChartInstance = new Chart(projectTiersChartCtx, {
            type: 'bar',
            data: {
                labels: chartData.projectTiers.labels[lang].map(l => wrapLabel(l, 16)),
                datasets: [{
                    data: chartData.projectTiers.data,
                    backgroundColor: ['#3498DB', '#9B59B6', '#E67E22'],
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (tooltipItems) => {
                                const item = tooltipItems[0];
                                return chartData.projectTiers.tooltipTitles[lang][item.dataIndex];
                            },
                            label: (context) => {
                                const value = context.parsed.x;
                                return `${lang === 'en' ? 'Multiplier' : 'ضرب'}: ${value}x`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: lang === 'en' ? 'Multiplier' : 'ضرب',
                            color: '#424242',
                        },
                        ticks: { color: '#424242' }
                    },
                    y: {
                        ticks: { color: '#424242' }
                    }
                }
            }
        });

        bonusTiersChartInstance = new Chart(bonusTiersChartCtx, {
            type: 'bar',
            data: {
                labels: chartData.bonusTiers.labels[lang].map(l => wrapLabel(l, 16)),
                datasets: [{
                    data: chartData.bonusTiers.data,
                    backgroundColor: ['#E67E22', '#3498DB', '#9B59B6'],
                }]
            },
            options: {
                indexAxis: 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (tooltipItems) => {
                                const item = tooltipItems[0];
                                return chartData.bonusTiers.labels[lang][item.dataIndex];
                            },
                            label: (context) => {
                                return `${lang === 'en' ? 'Points' : 'پوائنٹس'}: ${chartData.bonusTiers.ranges[lang][context.dataIndex]}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: lang === 'en' ? 'Points Required' : 'مطلوبہ پوائنٹس',
                            color: '#424242',
                        },
                        ticks: { color: '#424242' }
                    },
                    y: {
                        ticks: { color: '#424242' }
                    }
                }
            }
        });
    };

    const toggleLanguage = (lang) => {
        if (lang === 'ur') {
            contentEnglish.classList.add('hidden');
            contentUrdu.classList.remove('hidden');
            englishBtn?.classList.remove('active');
            urduBtn?.classList.add('active');
            playFade(contentUrdu);
        } else {
            contentUrdu.classList.add('hidden');
            contentEnglish.classList.remove('hidden');
            urduBtn?.classList.remove('active');
            englishBtn?.classList.add('active');
            playFade(contentEnglish);
        }
        createCharts(lang);
        // retrigger chart container appear animation
        document.querySelectorAll('.chart-container').forEach(c => {
            c.classList.remove('chart-appear');
            void c.offsetWidth; // reflow to restart animation
            c.classList.add('chart-appear');
        });
    };

    englishBtn?.addEventListener('click', () => toggleLanguage('en'));
    urduBtn?.addEventListener('click', () => toggleLanguage('ur'));

    toggleLanguage('en');
    // Setup reveal-on-scroll
    applyRevealClasses();
    setupScrollReveal();
});
</script>
<template>
    <Head title="Bonus System" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bonus System</h2>
        </template>
        <div class="bonus-root text-gray-800">
            <div class="container mx-auto p-4 md:p-12">

                <!-- Header -->
                <header class="text-center mb-16">
                    <h1 class="text-5xl md:text-7xl font-extrabold text-black mb-4 leading-tight tracking-tighter">
                        Reward System V1.0 ✨
                    </h1>
                    <p class="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto">
                        A professional guide to our new points and bonus system, designed to reward your hard work and consistent performance.
                    </p>
                    <div class="mt-8 flex justify-center space-x-4">
                        <button id="english-btn" class="language-toggle-btn px-6 py-2 rounded-full font-semibold active">
                            English
                        </button>
                        <button id="urdu-btn" class="language-toggle-btn px-6 py-2 rounded-full font-semibold">
                            اردو
                        </button>
                    </div>
                </header>

                <!-- English Content -->
                <div id="content-english">

                    <!-- How to Earn Points Section -->
                    <section id="how-to-earn" class="mb-20">
                        <div class="text-4xl md:text-5xl font-bold text-center mb-12 brand-blue flex justify-center">
                            <iframe src="https://drive.google.com/file/d/1boKWXJFsHJqRs8tRS70qN0HM3jWeIshj/preview" width="640"
                                    height="480" allow="autoplay"></iframe>
                        </div>
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 brand-blue">How to Earn Points 🚀</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4">
                                    <span class="text-4xl">⏰</span>
                                    <h3 class="font-bold text-2xl text-gray-900">Daily Standups</h3>
                                </div>
                                <p class="text-gray-600 mb-6">
                                    Consistency is key. Earn points daily by checking in on time.
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-green">
                                        <span>On-time Standup</span>
                                        <span>+25 Points</span>
                                    </div>
                                    <div class="flex items-center justify-between text-brand-blue">
                                        <span>Weekly Streak</span>
                                        <span>+100 Bonus</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4">
                                    <span class="text-4xl">✅</span>
                                    <h3 class="font-bold text-2xl text-gray-900">Task Completion</h3>
                                </div>
                                <p class="text-gray-600 mb-6">
                                    Getting work done efficiently is a primary driver of success.
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-green">
                                        <span>On-time Completion</span>
                                        <span>+50 Points</span>
                                    </div>
                                    <div class="flex items-center justify-between text-brand-orange">
                                        <span>Early Completion</span>
                                        <span>+100 Bonus</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4">
                                    <span class="text-4xl">🤝</span>
                                    <h3 class="font-bold text-2xl text-gray-900">Peer Recognition</h3>
                                </div>
                                <p class="text-gray-600 mb-6">
                                    Acknowledge your colleagues' hard work to build a stronger team.
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-purple">
                                        <span>Manager-approved Kudos</span>
                                        <span>+25 Points</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Multiplier Effect Section -->
                    <section id="multipliers" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-purple">The Multiplier Effect 📊</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            The points you earn are multiplied based on a project's importance. This rewards you for work that has a bigger impact.
                        </p>
                        <div class="card-bg p-8 rounded-2xl shadow-xl">
                            <div class="chart-container">
                                <canvas id="projectTiersChart"></canvas>
                            </div>
                        </div>
                    </section>

                    <!-- Bonus & Awards Section -->
                    <section id="awards" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-orange">Bonus & Awards Overview 🏆</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            Bonuses are distributed at the end of each month from two separate pools: one for employees and one for contractors.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Employee Awards -->
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-3xl mb-6 text-brand-blue">🏆 Employee Awards</h3>
                                <div class="timeline-container">
                                    <div class="timeline-item">
                                        <div class="timeline-dot employee">1</div>
                                        <h4 class="font-bold text-xl mb-2 text-gray-900">High Achiever Awards</h4>
                                        <p class="text-sm text-gray-600">For those who rank highest on the monthly leaderboard.</p>
                                        <ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-600">
                                            <li>1st, 2nd, and 3rd Place for the highest points.</li>
                                            <li>"Most Improved" for the biggest point increase.</li>
                                        </ul>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot employee">2</div>
                                        <h4 class="font-bold text-xl mb-2 text-brand-orange">Consistent Contributor Bonus</h4>
                                        <p class="text-sm text-gray-600">Rewards all employees who reach a specific point goal each month.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Contractor Awards -->
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-3xl mb-6 text-brand-purple">🚀 Contractor Awards</h3>
                                <div class="timeline-container">
                                    <div class="timeline-item">
                                        <div class="timeline-dot contractor">1</div>
                                        <h4 class="font-bold text-xl mb-2 text-gray-900">Contractor of the Month</h4>
                                        <p class="text-sm text-gray-600">Awarded to the contractor with the highest total points for the month.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot contractor">2</div>
                                        <h4 class="font-bold text-xl mb-2 text-brand-orange">Project Performance Bonus</h4>
                                        <p class="text-sm text-gray-600">A separate bonus for high-quality project delivery.</p>
                                        <ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-600">
                                            <li>Awarded when a project is completed on time and within budget.</li>
                                            <li>Bonus is 5% of the project's agreed amount.</li>
                                            <li>An extra PKR 1,000 for outstanding client feedback.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Employee Bonus Breakdown Section -->
                    <section id="employee-bonuses" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-blue">Employee Bonus Breakdown 💼</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-purple">High Achiever Awards</h3>
                                <p class="text-gray-600 mb-4">
                                    The top-performing employees for the month are recognized and rewarded from a dedicated bonus pool. This includes:
                                </p>
                                <ul class="list-disc list-inside text-gray-600 space-y-2">
                                    <li>Awards for the first, second, and third highest point earners.</li>
                                    <li>A "Most Improved" award for the employee who shows the biggest increase in points from the previous month.</li>
                                </ul>
                            </div>
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-blue">Consistent Contributor Bonus</h3>
                                <p class="text-gray-600">
                                    This bonus is for all employees who achieve a specific point goal each month. The bonus amount is distributed from a fixed budget and adjusted proportionally among all qualifying employees to ensure fairness.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Contractor Bonus Breakdown Section -->
                    <section id="contractor-bonuses" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-purple">Contractor Bonus Breakdown 🛠️</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-orange">Project Performance & Top Contractor</h3>
                                <p class="text-gray-600 mb-4">
                                    Contractors can earn bonuses from two distinct categories:
                                </p>
                                <ul class="list-disc list-inside text-gray-600 space-y-2">
                                    <li>A **Project Performance Bonus** is awarded for completing project milestones on time and within budget. An additional bonus may be given for outstanding client feedback.</li>
                                    <li>A **Top Contractor Bonus** is a performance-based bonus for the highest point earners each month. The value of this bonus is calculated based on total points earned.</li>
                                    <li>The "Most Improved" award is also applicable to contractors who show significant growth in their monthly points.</li>
                                </ul>
                            </div>
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-blue">Monthly Bonus Pool</h3>
                                <p class="text-gray-600">
                                    The bonus pool for contractors is separate from the employee pool. This is a dedicated budget to reward the hard work and high-quality deliverables provided by our contractor team.
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Tiers Section -->
                    <section id="tiers" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 brand-blue">Consistent Contributor Tiers 📈</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            The Consistent Contributor Bonus rewards all employees who reach specific point goals each month.
                        </p>
                        <div class="card-bg p-8 rounded-2xl shadow-xl">
                            <div class="chart-container">
                                <canvas id="bonusTiersChart"></canvas>
                            </div>
<!--                            <div class="mt-8 text-center max-w-2xl mx-auto">-->
<!--                                <h3 class="text-2xl font-bold text-gray-900 mb-4">Bonus Amounts</h3>-->
<!--                                <p class="text-gray-600 mb-4">-->
<!--                                    The total budget for this category is fixed. If many people qualify, the bonus amounts will be proportionally adjusted.-->
<!--                                </p>-->
<!--                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 text-sm font-semibold">-->
<!--                                    <div class="p-4 rounded-lg bg-brand-orange text-white shadow-md">-->
<!--                                        <p class="text-lg">Bronze Tier</p>-->
<!--                                        <p>PKR 500</p>-->
<!--                                    </div>-->
<!--                                    <div class="p-4 rounded-lg bg-brand-blue text-white shadow-md">-->
<!--                                        <p class="text-lg">Silver Tier</p>-->
<!--                                        <p>PKR 1,000</p>-->
<!--                                    </div>-->
<!--                                    <div class="p-4 rounded-lg bg-brand-purple text-white shadow-md">-->
<!--                                        <p class="text-lg">Gold Tier</p>-->
<!--                                        <p>PKR 2,000</p>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
                        </div>
                    </section>

                    <!-- Call to Action Section -->
                    <section class="cta-card text-white p-12 md:p-20 rounded-3xl text-center mb-16">
                        <h2 class="text-4xl md:text-5xl font-bold mb-4 leading-snug">
                            Ready to Earn Your Bonus?
                        </h2>
                        <p class="text-lg md:text-xl font-light mb-8 max-w-3xl mx-auto">
                            Start by being consistent with your daily standups and delivering your tasks on time. Your hard work is valued here!
                        </p>
                        <Link :href="`/leaderboard`" class="bg-white text-gray-900 px-8 py-4 rounded-full font-bold text-xl hover:bg-gray-200 transition-colors duration-300">
                            Check Your Progress
                        </Link>
                    </section>
                </div>

                <!-- Urdu Content -->
                <div id="content-urdu" class="hidden text-right" dir="rtl">
                    <header class="text-center mb-16">
                        <h1 class="text-5xl md:text-7xl font-extrabold text-black mb-4 leading-tight tracking-tighter">
                            انعامات کا نظام V1.0 ✨
                        </h1>
                        <p class="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto">
                            ہمارے نئے پوائنٹس اور بونس سسٹم کی ایک پیشہ ورانہ گائیڈ، جو آپ کی محنت اور مسلسل کارکردگی کو انعام دینے کے لیے ڈیزائن کیا گیا ہے۔
                        </p>
                    </header>

                    <!-- How to Earn Points Section (Urdu) -->
                    <section id="how-to-earn-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 brand-blue">پوائنٹس کیسے کمائیں 🚀</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4 justify-end">
                                    <h3 class="font-bold text-2xl text-gray-900">روزانہ اسٹینڈ اپ</h3>
                                    <span class="text-4xl">⏰</span>
                                </div>
                                <p class="text-gray-600 mb-6 text-right">
                                    مستقل مزاجی ضروری ہے! ہر روز وقت پر چیک ان کر کے پوائنٹس حاصل کریں۔
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-green">
                                        <span>وقت پر اسٹینڈ اپ</span>
                                        <span>25+ پوائنٹس</span>
                                    </div>
                                    <div class="flex items-center justify-between text-brand-blue">
                                        <span>ہفتہ وار لڑی</span>
                                        <span>100+ بونس</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4 justify-end">
                                    <h3 class="font-bold text-2xl text-gray-900">ٹاسک کی تکمیل</h3>
                                    <span class="text-4xl">✅</span>
                                </div>
                                <p class="text-gray-600 mb-6 text-right">
                                    کام کو مؤثر طریقے سے اور وقت پر مکمل کرنا کامیابی کی کنجی ہے۔
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-green">
                                        <span>وقت پر تکمیل</span>
                                        <span>50+ پوائنٹس</span>
                                    </div>
                                    <div class="flex items-center justify-between text-brand-orange">
                                        <span>وقت سے پہلے تکمیل</span>
                                        <span>100+ بونس</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-bg p-8 rounded-2xl shadow-xl hover:shadow-2xl">
                                <div class="flex items-center space-x-4 mb-4 justify-end">
                                    <h3 class="font-bold text-2xl text-gray-900">ساتھیوں کی پہچان</h3>
                                    <span class="text-4xl">🤝</span>
                                </div>
                                <p class="text-gray-600 mb-6 text-right">
                                    اپنے ساتھیوں کی محنت کو تسلیم کرنا ٹیم کی ثقافت کو مضبوط کرتا ہے۔
                                </p>
                                <div class="space-y-4 text-sm font-semibold">
                                    <div class="flex items-center justify-between text-brand-purple">
                                        <span>مینیجر سے منظور شدہ Kudos</span>
                                        <span>25+ پوائنٹس</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Multiplier Effect Section (Urdu) -->
                    <section id="multipliers-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-purple">ملٹیپلائر کا اثر 📊</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            آپ جو پوائنٹس حاصل کرتے ہیں وہ پراجیکٹ کی اہمیت کی بنیاد پر ضرب ہوتے ہیں۔ یہ آپ کو اس کام کے لیے انعام دیتا ہے جو کاروبار پر بڑا اثر ڈالتا ہے۔
                        </p>
                        <div class="card-bg p-8 rounded-2xl shadow-xl">
                            <div class="chart-container">
                                <canvas id="projectTiersChartUrdu"></canvas>
                            </div>
                        </div>
                    </section>

                    <!-- Bonus & Awards Section (Urdu) -->
                    <section id="awards-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-orange">بونس اور انعامات کا جائزہ 🏆</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            ہر مہینے کے آخر میں، ہم دو الگ الگ پولز سے بونس تقسیم کرتے ہیں: ایک ملازمین کے لیے اور ایک کنٹریکٹرز کے لیے۔
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Employee Awards (Urdu) -->
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-3xl mb-6 text-brand-blue">🏆 ملازمین کے انعامات</h3>
                                <div class="timeline-container">
                                    <div class="timeline-item">
                                        <div class="timeline-dot employee">1</div>
                                        <h4 class="font-bold text-xl mb-2 text-gray-900">اعلی کارکردگی دکھانے والوں کے انعامات</h4>
                                        <p class="text-sm text-gray-600">ان لوگوں کے لیے جو ماہانہ لیڈر بورڈ پر سرفہرست ہیں۔</p>
                                        <ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-600 pr-4">
                                            <li>پہلی، دوسری اور تیسری پوزیشن سب سے زیادہ پوائنٹس کے لیے۔</li>
                                            <li>"بہترین بہتری" سب سے زیادہ پوائنٹس میں اضافے کے لیے۔</li>
                                        </ul>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot employee">2</div>
                                        <h4 class="font-bold text-xl mb-2 text-brand-orange">مسلسل کام کرنے والے کا بونس</h4>
                                        <p class="text-sm text-gray-600">ان تمام ملازمین کو انعام دیتا ہے جو ہر مہینے ایک مخصوص پوائنٹ کا ہدف حاصل کرتے ہیں۔</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Contractor Awards (Urdu) -->
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-3xl mb-6 text-brand-purple">🚀 کنٹریکٹر کے انعامات</h3>
                                <div class="timeline-container">
                                    <div class="timeline-item">
                                        <div class="timeline-dot contractor">1</div>
                                        <h4 class="font-bold text-xl mb-2 text-gray-900">مہینے کا کنٹریکٹر</h4>
                                        <p class="text-sm text-gray-600">مہینے کے سب سے زیادہ پوائنٹس حاصل کرنے والے کنٹریکٹر کو دیا جاتا ہے۔</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot contractor">2</div>
                                        <h4 class="font-bold text-xl mb-2 text-brand-orange">پراجیکٹ کارکردگی بونس</h4>
                                        <p class="text-sm text-gray-600">اعلی معیار کے پراجیکٹ کی ڈیلیوری کے لیے ایک الگ بونس۔</p>
                                        <ul class="list-disc list-inside text-sm mt-2 space-y-1 text-gray-600 pr-4">
                                            <li>یہ تب دیا جاتا ہے جب کوئی پراجیکٹ وقت پر اور بجٹ کے اندر مکمل ہو۔</li>
                                            <li>بونس پراجیکٹ کی طے شدہ رقم کا 5% ہوتا ہے۔</li>
                                            <li>بہترین کلائنٹ فیڈ بیک کے لیے PKR 1,000 کا اضافی انعام۔</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Employee Bonus Breakdown Section (Urdu) -->
                    <section id="employee-bonuses-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-blue">ملازمین کے بونس کی تفصیل 💼</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-purple">اعلی کارکردگی دکھانے والوں کے انعامات</h3>
                                <p class="text-gray-600 mb-4">
                                    ماہ کے دوران بہترین کارکردگی دکھانے والے ملازمین کو ایک مخصوص بونس پول سے تسلیم کیا جاتا ہے اور انعام دیا جاتا ہے۔ اس میں شامل ہیں:
                                </p>
                                <ul class="list-disc list-inside text-gray-600 space-y-2 pr-4">
                                    <li>سب سے زیادہ پوائنٹس حاصل کرنے والوں کے لیے پہلی، دوسری اور تیسری پوزیشن کے انعامات۔</li>
                                    <li>"بہترین بہتری" کا انعام اس ملازم کے لیے ہے جس کے پوائنٹس میں پچھلے مہینے کے مقابلے میں سب سے زیادہ اضافہ ہوا ہو۔</li>
                                </ul>
                            </div>
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-blue">مسلسل کام کرنے والے کا بونس</h3>
                                <p class="text-gray-600">
                                    یہ بونس ان تمام ملازمین کے لیے ہے جو ہر مہینے ایک مخصوص پوائنٹ کا ہدف حاصل کرتے ہیں۔ بونس کی رقم ایک طے شدہ بجٹ سے تقسیم کی جاتی ہے اور تمام اہل ملازمین کے درمیان انصاف کو یقینی بنانے کے لیے متناسب طور پر ایڈجسٹ کی جاتی ہے۔
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Contractor Bonus Breakdown Section (Urdu) -->
                    <section id="contractor-bonuses-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 text-brand-purple">کنٹریکٹر کے بونس کی تفصیل 🛠️</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-orange">پراجیکٹ کارکردگی اور سرفہرست کنٹریکٹر</h3>
                                <p class="text-gray-600 mb-4">
                                    کنٹریکٹرز دو الگ الگ زمروں سے بونس حاصل کر سکتے ہیں:
                                </p>
                                <ul class="list-disc list-inside text-gray-600 space-y-2 pr-4">
                                    <li>**پراجیکٹ کارکردگی بونس** پراجیکٹ کے سنگ میلوں کو وقت پر اور بجٹ کے اندر مکمل کرنے پر دیا جاتا ہے۔ بہترین کلائنٹ فیڈ بیک کے لیے اضافی بونس بھی دیا جا سکتا ہے۔</li>
                                    <li>**سرفہرست کنٹریکٹر بونس** ہر مہینے سب سے زیادہ پوائنٹس حاصل کرنے والے کنٹریکٹرز کے لیے ایک کارکردگی پر مبنی بونس ہے۔ اس بونس کی قیمت حاصل کردہ کل پوائنٹس کی بنیاد پر شمار کی جاتی ہے۔</li>
                                    <li>"بہترین بہتری" کا انعام بھی ان کنٹریکٹرز پر لاگو ہوتا ہے جو اپنے ماہانہ پوائنٹس میں نمایاں اضافہ دکھاتے ہیں۔</li>
                                </ul>
                            </div>
                            <div class="card-bg p-8 rounded-2xl shadow-xl">
                                <h3 class="font-bold text-2xl mb-4 text-brand-blue">ماہانہ بونس پول</h3>
                                <p class="text-gray-600">
                                    کنٹریکٹرز کے لیے بونس پول ملازمین کے پول سے الگ ہے۔ یہ ایک مخصوص بجٹ ہے جو ہماری کنٹریکٹر ٹیم کی محنت اور اعلیٰ معیار کی ڈیلیوری کو انعام دینے کے لیے ہے۔
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Tiers Section (Urdu) -->
                    <section id="tiers-urdu" class="mb-20">
                        <h2 class="text-4xl md:text-5xl font-bold text-center mb-12 brand-blue">مسلسل کارکردگی دکھانے والے کے درجات 📈</h2>
                        <p class="text-center text-gray-600 mb-10 max-w-2xl mx-auto">
                            **مسلسل کام کرنے والے کا بونس** ان تمام ملازمین کو انعام دیتا ہے جو ہر مہینے پوائنٹس کے مخصوص اہداف حاصل کرتے ہیں۔
                        </p>
                        <div class="card-bg p-8 rounded-2xl shadow-xl">
                            <div class="chart-container">
                                <canvas id="bonusTiersChartUrdu"></canvas>
                            </div>
<!--                            <div class="mt-8 text-center max-w-2xl mx-auto">-->
<!--                                <h3 class="text-2xl font-bold text-gray-900 mb-4">بونس کی رقم</h3>-->
<!--                                <p class="text-gray-600 mb-4">-->
<!--                                    اس زمرے کا کل بجٹ فکس ہے۔ اگر بہت سے لوگ اہل ہوتے ہیں، تو بونس کی رقم کو بجٹ کے اندر رہنے کے لیے متناسب طور پر ایڈجسٹ کیا جائے گا۔-->
<!--                                </p>-->
<!--                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 text-sm font-semibold">-->
<!--                                    <div class="p-4 rounded-lg bg-brand-orange text-white shadow-md">-->
<!--                                        <p class="text-lg">کانسی کا ٹیر</p>-->
<!--                                        <p>PKR 500</p>-->
<!--                                    </div>-->
<!--                                    <div class="p-4 rounded-lg bg-brand-blue text-white shadow-md">-->
<!--                                        <p class="text-lg">چاندی کا ٹیر</p>-->
<!--                                        <p>PKR 1,000</p>-->
<!--                                    </div>-->
<!--                                    <div class="p-4 rounded-lg bg-brand-purple text-white shadow-md">-->
<!--                                        <p class="text-lg">سونے کا ٹیر</p>-->
<!--                                        <p>PKR 2,000</p>-->
<!--                                    </div>-->
<!--                                </div>-->
<!--                            </div>-->
                        </div>
                    </section>

                    <!-- Call to Action Section (Urdu) -->
                    <section class="cta-card text-white p-12 md:p-20 rounded-3xl text-center mb-16">
                        <h2 class="text-4xl md:text-5xl font-bold mb-4 leading-snug">
                            کیا آپ بونس کمانے کے لیے تیار ہیں؟
                        </h2>
                        <p class="text-lg md:text-xl font-light mb-8 max-w-3xl mx-auto">
                            اپنے روزانہ کے اسٹینڈ اپ کے ساتھ مستقل مزاجی اختیار کرکے اور اپنے کاموں کو وقت پر مکمل کرکے شروع کریں۔ آپ کی محنت کی یہاں قدر کی جاتی ہے!
                        </p>
                        <Link :href="`/leaderboard`" class="bg-white text-gray-900 px-8 py-4 rounded-full font-bold text-xl hover:bg-gray-200 transition-colors duration-300">
                            اپنی پیشرفت دیکھیں
                        </Link>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.bonus-root {
    --bg-light: #f4f7f9;
    --bg-card: #ffffff;
    --text-dark: #212121;
    --text-primary: #000000;
    --brand-purple: #9B59B6;
    --brand-blue: #3498DB;
    --brand-orange: #E67E22;
    --brand-green: #2ECC71;
    --accent-gray: #e0e0e0;
    background-color: var(--bg-light);
    color: var(--text-dark);
}

.bonus-root .card-bg {
    background-color: var(--bg-card);
    border: 1px solid var(--accent-gray);
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
}
.bonus-root .card-bg:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
}

.bonus-root .cta-card {
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-blue));
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.bonus-root .language-toggle-btn {
    background-color: var(--accent-gray);
    color: var(--text-dark);
    transition: all 0.3s ease;
}
.bonus-root .language-toggle-btn.active {
    background-color: var(--brand-purple);
    color: white;
    box-shadow: 0 4px 10px rgba(155, 89, 182, 0.4);
}
.bonus-root .language-toggle-btn:hover {
    background-color: #d1d1d1;
}

.bonus-root .chart-container {
    position: relative;
    width: 100%;
    height: 350px;
}

.bonus-root .timeline-container { position: relative; }
.bonus-root .timeline-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 1.5rem;
    width: 2px;
    background-color: var(--accent-gray);
    height: 100%;
}
.bonus-root .timeline-item {
    position: relative;
    padding-left: 3rem;
    margin-bottom: 2rem;
}
.bonus-root .timeline-dot {
    position: absolute;
    left: 1.5rem;
    top: 0.25rem;
    transform: translateX(-50%);
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.bonus-root .timeline-dot.employee { background-color: var(--brand-blue); }
.bonus-root .timeline-dot.contractor { background-color: var(--brand-purple); }

/* RTL support for Urdu */
#content-urdu .text-right { text-align: right; }
#content-urdu .timeline-container::before { left: auto; right: 1.5rem; }
#content-urdu .timeline-item { padding-left: 0; padding-right: 3rem; }
#content-urdu .timeline-dot { left: auto; right: 1.5rem; transform: translateX(50%); }
#content-urdu .list-disc li { list-style-position: outside; }
#content-urdu .list-disc { padding-right: 1.5rem; }

.bonus-root .progress-bar-container {
    background-color: var(--accent-gray);
    height: 8px;
    border-radius: 9999px;
}
.bonus-root .progress-bar {
    height: 100%;
    border-radius: 9999px;
}

/* Animations and effects */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes shimmerGradient {
    0% { background-position: 0% 50%; }
    100% { background-position: 100% 50%; }
}
@keyframes pulseGlow {
    from { box-shadow: 0 0 0 rgba(0,0,0,0.2); transform: scale(1); }
    to { box-shadow: 0 0 20px rgba(155, 89, 182, 0.35); transform: scale(1.03); }
}

/* Header gradient text with subtle shimmer */
.bonus-root header h1 {
    background: linear-gradient(90deg, var(--brand-purple), var(--brand-blue), var(--brand-orange));
    background-size: 200% 200%;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    animation: shimmerGradient 6s linear infinite;
}

/* Scroll reveal utility */
.reveal-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity .6s ease, transform .6s ease; }
.reveal-on-scroll.in-view { opacity: 1; transform: translateY(0); }

/* Quick fade class used on language switch */
.anim-fade-in { animation: fadeUp .6s ease forwards; }

/* Chart appear animation */
.chart-container { opacity: 0; transform: translateY(10px); transition: opacity .6s ease, transform .6s ease; }
.chart-container.chart-appear { opacity: 1; transform: translateY(0); }

/* Language buttons hover/active glow */
.bonus-root .language-toggle-btn { box-shadow: 0 0 0 rgba(0,0,0,0); transform: translateY(0); }
.bonus-root .language-toggle-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.1); }

/* Subtle pulse on timeline dots */
.bonus-root .timeline-dot { animation: pulseGlow 3.5s ease-in-out infinite alternate; }

/* CTA button pop on hover */
.bonus-root .cta-card button { transition: transform .25s ease, box-shadow .25s ease; }
.bonus-root .cta-card button:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 12px 24px rgba(0,0,0,0.15); }
</style>
