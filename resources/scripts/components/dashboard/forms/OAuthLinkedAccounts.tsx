import { useEffect, useState } from 'react';

import { getLinkedOAuthProviders, linkOAuthProvider, unlinkOAuthProvider, LinkedOAuthProvider } from '@/api/account/oauth';

interface OAuthProviderConfig {
    key: string;
    label: string;
    color: string;
    hoverColor: string;
    icon: string;
    type: 'redirect' | 'telegram';
    botId?: string;
}

const OAUTH_ICONS: Record<string, JSX.Element> = {
    telegram: (
        <svg className='w-5 h-5' viewBox='0 0 24 24' fill='currentColor'>
            <path d='M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.892-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z' />
        </svg>
    ),
    discord: (
        <svg className='w-5 h-5' viewBox='0 0 24 24' fill='currentColor'>
            <path d='M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z' />
        </svg>
    ),
};

const OAuthLinkedAccounts = () => {
    const [linked, setLinked] = useState<Record<string, LinkedOAuthProvider>>({});
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const providers: OAuthProviderConfig[] = (window as any).SiteConfiguration?.oauth || [];

    useEffect(() => {
        getLinkedOAuthProviders()
            .then(setLinked)
            .catch(() => setError('Failed to load linked accounts.'))
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        // Load Telegram widget script if needed
        const hasTelegram = providers.some((p) => p.type === 'telegram');
        if (hasTelegram && !document.querySelector('script[src*="telegram-widget.js"]')) {
            const script = document.createElement('script');
            script.src = 'https://telegram.org/js/telegram-widget.js?22';
            script.async = true;
            document.head.appendChild(script);
        }
    }, []);

    const handleLink = (provider: OAuthProviderConfig) => {
        setError(null);
        setSuccess(null);

        if (provider.type === 'telegram') {
            if (!provider.botId) return;

            (window as any).Telegram?.Login?.auth(
                { bot_id: provider.botId, request_access: 'write' },
                (data: any) => {
                    if (!data) return;

                    setActionLoading(provider.key);
                    linkOAuthProvider(provider.key, data)
                        .then(() => {
                            setSuccess(`${provider.label} linked successfully.`);
                            return getLinkedOAuthProviders().then(setLinked);
                        })
                        .catch((err: any) => {
                            setError(err?.response?.data?.error || `Failed to link ${provider.label}.`);
                        })
                        .finally(() => setActionLoading(null));
                }
            );
        } else {
            // Standard OAuth redirect flow — redirect to auth page, callback will link
            window.location.href = `/auth/oauth/link/${provider.key}`;
        }
    };

    const handleUnlink = (provider: OAuthProviderConfig) => {
        setError(null);
        setSuccess(null);
        setActionLoading(provider.key);

        unlinkOAuthProvider(provider.key)
            .then(() => {
                setSuccess(`${provider.label} unlinked.`);
                const updated = { ...linked };
                delete updated[provider.key];
                setLinked(updated);
            })
            .catch((err: any) => {
                setError(err?.response?.data?.error || `Failed to unlink ${provider.label}.`);
            })
            .finally(() => setActionLoading(null));
    };

    if (providers.length === 0) return null;

    return (
        <div>
            {error && (
                <div className='mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-sm'>
                    {error}
                </div>
            )}
            {success && (
                <div className='mb-4 p-3 rounded-lg bg-green-500/10 border border-green-500/20 text-green-400 text-sm'>
                    {success}
                </div>
            )}

            <p className='text-sm mb-4 text-zinc-400'>
                Link your accounts to enable quick login via OAuth providers.
            </p>

            {loading ? (
                <div className='text-sm text-zinc-500'>Loading...</div>
            ) : (
                <div className='flex flex-col gap-3'>
                    {providers.map((provider) => {
                        const isLinked = !!linked[provider.key];
                        const isLoading = actionLoading === provider.key;

                        return (
                            <div
                                key={provider.key}
                                className='flex items-center justify-between p-3 rounded-lg bg-[#ffffff06] border border-[#ffffff11]'
                            >
                                <div className='flex items-center gap-3'>
                                    <div style={{ color: provider.color }}>
                                        {OAUTH_ICONS[provider.icon] || null}
                                    </div>
                                    <div>
                                        <span className='text-sm font-semibold text-zinc-200'>
                                            {provider.label}
                                        </span>
                                        {isLinked && (
                                            <span className='ml-2 text-xs text-green-400'>Linked</span>
                                        )}
                                    </div>
                                </div>

                                <button
                                    type='button'
                                    onClick={() => (isLinked ? handleUnlink(provider) : handleLink(provider))}
                                    disabled={isLoading}
                                    className={`px-4 py-1.5 text-xs font-bold rounded-full transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-wait ${isLinked
                                            ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30 border border-red-500/30'
                                            : 'text-white hover:shadow-lg'
                                        }`}
                                    style={
                                        !isLinked
                                            ? { backgroundColor: provider.color }
                                            : undefined
                                    }
                                    onMouseEnter={(e) => {
                                        if (!isLinked) e.currentTarget.style.backgroundColor = provider.hoverColor;
                                    }}
                                    onMouseLeave={(e) => {
                                        if (!isLinked) e.currentTarget.style.backgroundColor = provider.color;
                                    }}
                                >
                                    {isLoading ? '...' : isLinked ? 'Unlink' : 'Link'}
                                </button>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
};

export default OAuthLinkedAccounts;
