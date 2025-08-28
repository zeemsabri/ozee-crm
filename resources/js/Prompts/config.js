// Config for user-data prompts to run post-login.
// Each item defines how to detect missing data and how to render the field.

export default [
  {
    id: 'users.timezone',
    model: 'users',
    column: 'timezone',
    title: 'Set your timezone',
    description:
      'We could not detect your timezone during login. Please select your timezone to get correct schedule times.',
    // Render as component for richer UX (uses existing TimezoneSelect)
    input: {
      type: 'component',
      component: 'TimezoneSelect',
      props: {},
    },
    // API details (generic endpoint that maps field/value)
    api: {
      endpoint: '/api/user/update-profile-field',
      method: 'post',
      fieldParam: 'field',
      valueParam: 'value',
    },
    // Function used by orchestrator to check if this prompt is needed
    isMissing: (user) => !user?.timezone,
    // How to map to API payload
    toPayload: (value) => ({ field: 'timezone', value }),
  },
];
