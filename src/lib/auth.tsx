// Browser authentication is held in an HttpOnly cookie issued by the API.
// No auth bearer token is persisted in localStorage/sessionStorage.
export const getAuthToken = (): string | null => null;

export const withAuthHeaders = (headers: HeadersInit = {}): HeadersInit => ({
  ...headers,
});
