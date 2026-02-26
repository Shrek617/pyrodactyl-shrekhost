import http from '@/api/http';

export interface LinkedOAuthProvider {
    provider: string;
    provider_id: string;
    created_at: string;
}

export const getLinkedOAuthProviders = (): Promise<Record<string, LinkedOAuthProvider>> => {
    return http.get('/api/client/account/oauth').then((res) => res.data.data);
};

export const linkOAuthProvider = (provider: string, data: Record<string, string>): Promise<void> => {
    return http.post(`/api/client/account/oauth/${provider}`, data);
};

export const unlinkOAuthProvider = (provider: string): Promise<void> => {
    return http.delete(`/api/client/account/oauth/${provider}`);
};
