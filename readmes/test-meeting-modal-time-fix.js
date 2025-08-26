// Test script for MeetingModal time selection fix
console.log('Testing MeetingModal time selection fix');

// Simulate the original formatDateForInput function (with the bug)
function originalFormatDateForInput(date) {
    const d = new Date(date);
    // Format as YYYY-MM-DDThh:mm
    return d.toISOString().slice(0, 16);
}

// Simulate the fixed formatDateForInput function
function fixedFormatDateForInput(date) {
    const d = new Date(date);
    // Format as YYYY-MM-DDThh:mm while preserving local timezone
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// Test with a specific time
const testDate = new Date('2025-07-24T14:20:00');
console.log('Test date (local):', testDate.toString());

// Test original function
const originalFormatted = originalFormatDateForInput(testDate);
console.log('Original function output:', originalFormatted);

// Parse the original formatted string back to a date
const parsedOriginal = new Date(originalFormatted);
console.log('Parsed original:', parsedOriginal.toString());
console.log('Hours difference:', parsedOriginal.getHours() - testDate.getHours());

// Test fixed function
const fixedFormatted = fixedFormatDateForInput(testDate);
console.log('Fixed function output:', fixedFormatted);

// Parse the fixed formatted string back to a date
const parsedFixed = new Date(fixedFormatted);
console.log('Parsed fixed:', parsedFixed.toString());
console.log('Hours difference:', parsedFixed.getHours() - testDate.getHours());

// Demonstrate the issue with a full cycle (select time -> save -> display)
console.log('\nSimulating full cycle (select time -> save -> display):');

// User selects a time (e.g., 2:20 PM)
const userSelectedTime = new Date('2025-07-24T14:20:00');
console.log('User selected time:', userSelectedTime.toString());

// Original implementation
console.log('\nOriginal implementation:');
// 1. Format for input display
const originalInputFormat = originalFormatDateForInput(userSelectedTime);
console.log('1. Formatted for input:', originalInputFormat);

// 2. User submits form, time is sent to backend
const originalBackendFormat = originalInputFormat.replace('T', ' ') + ':00';
console.log('2. Sent to backend:', originalBackendFormat);

// 3. Later, when displaying the meeting again
const originalDisplayTime = new Date(originalInputFormat);
console.log('3. Displayed to user:', originalDisplayTime.toString());
console.log('Time shift occurred:', originalDisplayTime.getHours() !== userSelectedTime.getHours());

// Fixed implementation
console.log('\nFixed implementation:');
// 1. Format for input display
const fixedInputFormat = fixedFormatDateForInput(userSelectedTime);
console.log('1. Formatted for input:', fixedInputFormat);

// 2. User submits form, time is sent to backend
const fixedBackendFormat = fixedInputFormat.replace('T', ' ') + ':00';
console.log('2. Sent to backend:', fixedBackendFormat);

// 3. Later, when displaying the meeting again
const fixedDisplayTime = new Date(fixedInputFormat);
console.log('3. Displayed to user:', fixedDisplayTime.toString());
console.log('Time shift occurred:', fixedDisplayTime.getHours() !== userSelectedTime.getHours());
