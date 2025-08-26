// Test script for tab refresh functionality
console.log('Testing tab refresh functionality...');

// Function to simulate clicking on a tab
function clickTab(tabId) {
  console.log(`Clicking on tab: ${tabId}`);
  const tabButton = document.querySelector(`button[data-tab-id="${tabId}"]`);
  if (tabButton) {
    tabButton.click();
    console.log(`Successfully clicked on tab: ${tabId}`);
  } else {
    console.error(`Tab button not found for: ${tabId}`);
  }
}

// Function to simulate clicking the refresh button
function clickRefresh() {
  console.log('Clicking refresh button...');
  const refreshButton = document.querySelector('button[data-tab-id]');
  if (refreshButton) {
    refreshButton.click();
    console.log('Successfully clicked refresh button');
  } else {
    console.error('Refresh button not found');
  }
}

// Test sequence
setTimeout(() => {
  console.log('Starting test sequence...');

  // Test each tab
  const tabs = ['new', 'all', 'waiting'];

  tabs.forEach((tabId, index) => {
    setTimeout(() => {
      console.log(`\nTesting tab: ${tabId}`);
      clickTab(tabId);

      // Wait a bit and then click refresh
      setTimeout(() => {
        clickRefresh();

        // Check console for any errors
        console.log(`Completed test for tab: ${tabId}`);
      }, 1000);
    }, index * 3000); // Stagger the tests
  });

  // Final check
  setTimeout(() => {
    console.log('\nTest sequence completed. Check console for any errors.');
  }, tabs.length * 3000 + 1000);
}, 1000);

// Instructions:
// 1. Open the Inbox page in the browser
// 2. Open browser developer console
// 3. Copy and paste this script into the console
// 4. Watch for any errors in the console output
