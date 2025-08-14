<!-- Awards View Component -->
<script>
import { defineComponent, ref } from 'vue';
import { ChevronDown, ChevronUp } from 'lucide-react';

const Accordion = defineComponent({
    props: ['title'],
    setup() {
        const isOpen = ref(false);
        return { isOpen };
    },
    template: `
        <div class="border border-gray-200 rounded-lg overflow-hidden shadow-lg">
            <button @click="isOpen = !isOpen" class="flex items-center justify-between w-full p-4 bg-gray-50 hover:bg-gray-100 transition-colors duration-150 text-left font-semibold text-lg">
                {{ title }}
                <component :is="isOpen ? ChevronUp : ChevronDown" class="h-5 w-5 text-gray-500" />
            </button>
            <div v-show="isOpen" class="bg-white transition-all duration-300 ease-in-out">
                <slot></slot>
            </div>
        </div>
    `,
});

const AwardsView = defineComponent({
    props: ['awardsDetails', 'users', 'formatCurrency'],
    components: { Accordion, ChevronDown, ChevronUp },
    setup(props) {
        const getUserName = (userId) => {
            const user = props.users.find(u => u.user_id === userId);
            return user ? user.name : 'Unknown User';
        };

        return { getUserName };
    },
    template: `
        <div class="space-y-6">
            <div v-for="(award, index) in awardsDetails" :key="index">
                <Accordion :title="award.award_name + ' (' + award.user_type + ')'">
                    <div class="p-4">
                        <p class="text-sm text-gray-500 mb-4">
                            Bonus Pool: <span class="font-semibold text-gray-900">{{ formatCurrency(award.bonus_pool_pkr || 0) }}</span> | Distributed: <span class="font-semibold text-gray-900">{{ formatCurrency(award.distributed_pkr || 0) }}</span>
                        </p>
                        <table class="w-full table-auto text-left">
                            <thead class="text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="p-2">Name</th>
                                <th class="p-2">Award</th>
                                <th class="p-2 text-right">Amount</th>
                                <th class="p-2">Details</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="(recipient, i) in award.recipients" :key="i" class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50 transition-colors duration-150">
                                <td class="p-2 font-semibold">{{ getUserName(recipient.user_id) }}</td>
                                <td class="p-2">
                                    <div v-if="recipient.awards">
                                        <span class="italic text-gray-500">Multiple Awards</span>
                                        <ul class="list-disc list-inside mt-2 text-sm text-gray-600">
                                            <li v-for="subAward in recipient.awards" :key="subAward.award_title">
                                                {{ subAward.award_title }} ({{ formatCurrency(subAward.amount_pkr) }})
                                            </li>
                                        </ul>
                                    </div>
                                    <span v-else>{{ recipient.award_title }}</span>
                                </td>
                                <td class="p-2 text-right text-green-600">
                                    <div v-if="recipient.awards">
                                        {{ formatCurrency(recipient.awards.reduce((acc, curr) => acc + (Number(curr.amount_pkr) || 0), 0)) }}
                                    </div>
                                    <div v-else>
                                        {{ formatCurrency(recipient.amount_pkr) }}
                                    </div>
                                </td>
                                <td class="p-2 text-gray-500 text-sm">
                                    {{ recipient.bonus_details || recipient.project_details?.bonus_reason }}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </Accordion>
            </div>
        </div>
    `,
});
export { AwardsView };
</script>
