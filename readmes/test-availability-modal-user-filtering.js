// Test script to verify that AvailabilityModal.vue correctly fetches availabilities for a single user only
// and that parent components correctly pass the userId prop

// Import the necessary dependencies
import { mount } from '@vue/test-utils';
import { createInertiaApp } from '@inertiajs/vue3';
import AvailabilityModal from '@/Components/Availability/AvailabilityModal.vue';
import AvailabilityBlocker from '@/Components/Availability/AvailabilityBlocker.vue';
import AvailabilityCalendar from '@/Components/Availability/AvailabilityCalendar.vue';
import AvailabilityPrompt from '@/Components/Availability/AvailabilityPrompt.vue';
import axios from 'axios';

// Mock axios
jest.mock('axios');

// Mock Inertia's usePage
jest.mock('@inertiajs/vue3', () => ({
  ...jest.requireActual('@inertiajs/vue3'),
  usePage: jest.fn(() => ({
    props: {
      auth: {
        user: {
          id: 789,
          name: 'Test User'
        }
      }
    }
  }))
}));

describe('AvailabilityModal.vue', () => {
  it('fetches availabilities for a specific user only', async () => {
    // Mock axios.get to return a successful response
    axios.get.mockResolvedValue({
      data: {
        availabilities: [],
        start_date: '2025-07-24',
        end_date: '2025-07-31'
      }
    });

    // Mount the component with a specific userId
    const wrapper = mount(AvailabilityModal, {
      props: {
        show: true,
        userId: 123 // Specific user ID
      }
    });

    // Wait for the component to mount and fetch data
    await wrapper.vm.$nextTick();

    // Check that axios.get was called with the correct parameters
    expect(axios.get).toHaveBeenCalledWith('/api/availabilities', {
      params: {
        start_date: expect.any(String),
        end_date: expect.any(String),
        user_id: 123 // Should include the user_id parameter
      }
    });

    // Clean up
    wrapper.unmount();
  });

  it('uses the provided userId when fetching availabilities', async () => {
    // Mock axios.get to return a successful response
    axios.get.mockResolvedValue({
      data: {
        availabilities: [],
        start_date: '2025-07-24',
        end_date: '2025-07-31'
      }
    });

    // Mount the component with a different userId
    const wrapper = mount(AvailabilityModal, {
      props: {
        show: true,
        userId: 456 // Different user ID
      }
    });

    // Wait for the component to mount and fetch data
    await wrapper.vm.$nextTick();

    // Check that axios.get was called with the correct parameters
    expect(axios.get).toHaveBeenCalledWith('/api/availabilities', {
      params: {
        start_date: expect.any(String),
        end_date: expect.any(String),
        user_id: 456 // Should include the different user_id parameter
      }
    });

    // Clean up
    wrapper.unmount();
  });
});

describe('Parent Components', () => {
  beforeEach(() => {
    // Reset axios mocks before each test
    jest.clearAllMocks();

    // Mock axios.get to return a successful response for all tests
    axios.get.mockResolvedValue({
      data: {
        availabilities: [],
        start_date: '2025-07-24',
        end_date: '2025-07-31',
        should_show_prompt: true,
        should_block_user: true,
        all_weekdays_covered: false,
        current_day: 5,
        is_thursday_to_saturday: true,
        next_week_start: '2025-07-28',
        next_week_end: '2025-08-01'
      }
    });
  });

  it('AvailabilityBlocker passes the current user ID to AvailabilityModal', async () => {
    // Mount the AvailabilityBlocker component
    const wrapper = mount(AvailabilityBlocker, {
      global: {
        stubs: {
          AvailabilityModal: {
            template: '<div></div>',
            props: ['userId']
          }
        }
      }
    });

    // Trigger the modal to open
    await wrapper.vm.openAvailabilityModal();
    await wrapper.vm.$nextTick();

    // Find the AvailabilityModal component
    const modal = wrapper.findComponent({ name: 'AvailabilityModal' });

    // Check that the userId prop is passed correctly
    expect(modal.props('userId')).toBe(789); // ID from the mocked usePage

    // Clean up
    wrapper.unmount();
  });

  it('AvailabilityCalendar passes the selected user ID to AvailabilityModal', async () => {
    // Mount the AvailabilityCalendar component with a specific userId
    const wrapper = mount(AvailabilityCalendar, {
      props: {
        userId: 123
      },
      global: {
        stubs: {
          AvailabilityModal: {
            template: '<div></div>',
            props: ['userId']
          },
          SingleDateAvailabilityModal: true
        }
      }
    });

    // Set a selected date and open the modal
    wrapper.vm.selectedDate = '2025-07-24';
    await wrapper.vm.openAvailabilityModal('2025-07-24');
    await wrapper.vm.$nextTick();

    // Find the AvailabilityModal component
    const modal = wrapper.findComponent({ name: 'AvailabilityModal' });

    // Check that the userId prop is passed correctly
    expect(modal.props('userId')).toBe(123);

    // Clean up
    wrapper.unmount();
  });

  it('AvailabilityPrompt passes the current user ID to AvailabilityModal', async () => {
    // Mount the AvailabilityPrompt component
    const wrapper = mount(AvailabilityPrompt, {
      global: {
        stubs: {
          AvailabilityModal: {
            template: '<div></div>',
            props: ['userId']
          }
        }
      }
    });

    // Trigger the modal to open
    await wrapper.vm.openAvailabilityModal();
    await wrapper.vm.$nextTick();

    // Find the AvailabilityModal component
    const modal = wrapper.findComponent({ name: 'AvailabilityModal' });

    // Check that the userId prop is passed correctly
    expect(modal.props('userId')).toBe(789); // ID from the mocked usePage

    // Clean up
    wrapper.unmount();
  });
});
