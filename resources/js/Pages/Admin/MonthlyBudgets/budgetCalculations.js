// Proportions for awards as a percentage of their respective pools
const HIGH_ACHIEVER_POOL_PERCENTAGE = 20000 / 35000;
const CONSISTENT_CONTRIBUTOR_POOL_PERCENTAGE = 15000 / 35000;

const FIRST_PLACE_PERCENTAGE = 10000 / 20000;
const SECOND_PLACE_PERCENTAGE = 5000 / 20000;
const THIRD_PLACE_PERCENTAGE = 2500 / 20000;
const MOST_IMPROVED_PERCENTAGE = 2500 / 20000;
const CONTRACTOR_OF_THE_MONTH_PERCENTAGE = 5000 / 15000;


/**
 * Parses a user input string to determine a bonus pool amount.
 * The input can be a fixed number (e.g., "10000") or a percentage (e.g., "20%").
 * @param {string|number} input - The user's input.
 * @param {number} totalBudget - The total monthly budget to calculate percentages from.
 * @returns {number|null} The calculated pool amount or null if input is invalid.
 */
const parsePoolInput = (input, totalBudget) => {
    if (typeof input === 'string' && input.endsWith('%')) {
        const percentage = parseFloat(input) / 100;
        return totalBudget * percentage;
    }
    const amount = parseFloat(input);
    return isNaN(amount) ? null : amount;
};

/**
 * Calculates the total bonus pool for employees.
 * This function first checks for a manual override and falls back to a proportional calculation.
 * @param {number} totalBudget - The total monthly budget.
 * @param {string|number} employeePoolInput - The user's input for the employee pool (amount or %).
 * @param {number} numEmployees - The number of employees.
 * @param {number} numContractors - The number of contractors.
 * @returns {number} The calculated employee bonus pool amount.
 */
export const calculateEmployeeBonusPool = (totalBudget, employeePoolInput, numEmployees, numContractors) => {
    const manualAmount = parsePoolInput(employeePoolInput, totalBudget);
    if (manualAmount !== null) {
        return manualAmount;
    }

    const totalPeople = numEmployees + numContractors;
    if (totalPeople > 0) {
        return (totalBudget / totalPeople) * numEmployees;
    }
    return 0;
};

/**
 * Calculates the total bonus pool for contractors.
 * This is the remainder of the total budget after the employee pool is defined.
 * @param {number} totalBudget - The total monthly budget.
 * @param {number} employeeBonusPool - The user-defined or calculated employee bonus pool.
 * @returns {number} The calculated contractor bonus pool amount.
 */
export const calculateContractorBonusPool = (totalBudget, employeeBonusPool) => {
    return totalBudget - employeeBonusPool;
};

/**
 * Calculates the high achiever pool for employees.
 * This is a percentage of the total employee bonus pool.
 * @param {number} employeeBonusPool - The employee bonus pool.
 * @returns {number} The calculated high achiever pool amount.
 */
export const calculateHighAchieverPool = (employeeBonusPool) => {
    return employeeBonusPool * HIGH_ACHIEVER_POOL_PERCENTAGE;
};

/**
 * Calculates the consistent contributor pool for employees.
 * This is a percentage of the total employee bonus pool.
 * @param {number} employeeBonusPool - The employee bonus pool.
 * @returns {number} The calculated consistent contributor pool amount.
 */
export const calculateConsistentContributorPool = (employeeBonusPool) => {
    return employeeBonusPool * CONSISTENT_CONTRIBUTOR_POOL_PERCENTAGE;
};

/**
 * Calculates the cash value of a single point.
 * @param {number} consistentContributorPool - The consistent contributor pool.
 * @param {number} teamTotalPoints - The total points earned by the team.
 * @returns {number} The calculated value of a single point.
 */
export const calculatePointsValue = (consistentContributorPool, teamTotalPoints) => {
    if (consistentContributorPool > 0 && teamTotalPoints > 0) {
        return consistentContributorPool / teamTotalPoints;
    }
    return 0;
};

// Exporting percentages for display purposes in the UI
export const awardPercentages = {
    first_place_award: FIRST_PLACE_PERCENTAGE,
    second_place_award: SECOND_PLACE_PERCENTAGE,
    third_place_award: THIRD_PLACE_PERCENTAGE,
    most_improved_award: MOST_IMPROVED_PERCENTAGE,
    contractor_of_the_month_award: CONTRACTOR_OF_THE_MONTH_PERCENTAGE
};
