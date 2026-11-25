jest.mock('@react-native-async-storage/async-storage', () =>
  require('@react-native-async-storage/async-storage/jest/async-storage-mock'),
);

jest.mock('@react-native-community/netinfo', () => ({
  __esModule: true,
  default: { fetch: jest.fn(), addEventListener: jest.fn(() => jest.fn()) },
}));

jest.mock('expo-router', () => ({
  router: { replace: jest.fn(), push: jest.fn() },
}));

jest.mock('expo-secure-store', () => ({
  isAvailableAsync: jest.fn(async () => true),
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

afterEach(() => {
  jest.clearAllMocks();
});
