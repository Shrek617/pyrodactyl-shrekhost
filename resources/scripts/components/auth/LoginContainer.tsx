import { useStoreState } from 'easy-peasy';
import type { FormikHelpers } from 'formik';
import { Formik } from 'formik';
import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { object, string } from 'yup';

import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Button from '@/components/elements/Button';
import Captcha, { getCaptchaResponse } from '@/components/elements/Captcha';
import Field from '@/components/elements/Field';
import Logo from '@/components/elements/PyroLogo';

import CaptchaManager from '@/lib/captcha';

import login from '@/api/auth/login';

import useFlash from '@/plugins/useFlash';

interface OAuthProvider {
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
        <svg className='w-5 h-5 mr-3' viewBox='0 0 24 24' fill='currentColor'>
            <path d='M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.892-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z' />
        </svg>
    ),
    discord: (
        <svg className='w-5 h-5 mr-3' viewBox='0 0 24 24' fill='currentColor'>
            <path d='M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z' />
        </svg>
    ),
};

function OAuthButton({ provider }: { provider: OAuthProvider }) {
    const [scriptLoaded, setScriptLoaded] = useState(false);

    useEffect(() => {
        if (provider.type !== 'telegram') return;

        if (document.querySelector('script[src*="telegram-widget.js"]')) {
            setScriptLoaded(true);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://telegram.org/js/telegram-widget.js?22';
        script.async = true;
        script.onload = () => setScriptLoaded(true);
        document.head.appendChild(script);
    }, [provider.type]);

    const handleClick = () => {
        if (provider.type === 'telegram') {
            if (!provider.botId || !scriptLoaded) return;

            (window as any).Telegram?.Login?.auth(
                { bot_id: provider.botId, request_access: 'write' },
                (data: any) => {
                    if (!data) return;
                    const params = new URLSearchParams();
                    Object.keys(data).forEach((key) => params.set(key, data[key]));
                    window.location.href = `/auth/login/oauth/${provider.key}/callback?${params.toString()}`;
                }
            );
        } else {
            window.location.href = `/auth/login/oauth/${provider.key}`;
        }
    };

    const isDisabled = provider.type === 'telegram' && !scriptLoaded;

    return (
        <button
            type='button'
            onClick={handleClick}
            disabled={isDisabled}
            className='flex items-center justify-center w-full px-4 py-2.5 text-sm font-bold text-white transition-all duration-200 rounded-full cursor-pointer disabled:opacity-50 disabled:cursor-wait'
            style={{
                backgroundColor: provider.color,
            }}
            onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = provider.hoverColor)}
            onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = provider.color)}
        >
            {OAUTH_ICONS[provider.icon] || null}
            Login with {provider.label}
        </button>
    );
}

function OAuthProviders() {
    const providers: OAuthProvider[] = (window as any).SiteConfiguration?.oauth || [];

    if (providers.length === 0) return null;

    return (
        <>
            <div className='flex items-center my-6'>
                <div className='flex-1 border-t border-zinc-700'></div>
                <span className='px-4 text-xs font-semibold text-zinc-400 uppercase tracking-wider'>
                    Or continue with
                </span>
                <div className='flex-1 border-t border-zinc-700'></div>
            </div>

            <div className='flex flex-col gap-3'>
                {providers.map((provider) => (
                    <OAuthButton key={provider.key} provider={provider} />
                ))}
            </div>
        </>
    );
}

interface Values {
    user: string;
    password: string;
}

function LoginContainer() {
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const navigate = useNavigate();

    useEffect(() => {
        clearFlashes();
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // Get captcha response if enabled
        let loginData: any = values;
        if (CaptchaManager.isEnabled()) {
            const captchaResponse = getCaptchaResponse();
            const fieldName = CaptchaManager.getProviderInstance().getResponseFieldName();

            console.log('Captcha enabled, response:', captchaResponse, 'fieldName:', fieldName);

            if (fieldName) {
                if (captchaResponse) {
                    loginData = { ...values, [fieldName]: captchaResponse };
                    console.log('Adding captcha to login data:', loginData);
                } else {
                    // Captcha is enabled but no response - show error
                    console.error('Captcha enabled but no response available');
                    clearAndAddHttpError({ error: new Error('Please complete the captcha verification.') });
                    setSubmitting(false);
                    return;
                }
            }
        } else {
            console.log('Captcha not enabled');
        }

        login(loginData)
            .then((response) => {
                if (response.complete) {
                    window.location.href = response.intended || '/';
                    return;
                }
                navigate('/auth/login/checkpoint', { state: { token: response.confirmationToken } });
            })
            .catch((error: any) => {
                setSubmitting(false);

                if (error.code === 'InvalidCredentials') {
                    clearAndAddHttpError({ error: new Error('Invalid username or password. Please try again.') });
                } else if (error.code === 'DisplayException') {
                    clearAndAddHttpError({ error: new Error(error.detail || error.message) });
                } else {
                    clearAndAddHttpError({ error });
                }
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ user: '', password: '' }}
            validationSchema={object().shape({
                user: string().required('A username or email must be provided.'),
                password: string().required('Please enter your account password.'),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer className={`w-full flex`}>
                    <div className='flex h-12 mb-4 items-center w-full'>
                        <Logo />
                    </div>
                    <div aria-hidden className='my-8 bg-[#ffffff33] min-h-[1px]'></div>
                    <h2 className='text-xl font-extrabold mb-2'>Login</h2>

                    <Field id='user' type={'text'} label={'Username or Email'} name={'user'} disabled={isSubmitting} />

                    <div className={`relative mt-6`}>
                        <Field
                            id='password'
                            type={'password'}
                            label={'Password'}
                            name={'password'}
                            disabled={isSubmitting}
                        />
                        <Link
                            to={'/auth/password'}
                            className={`text-xs text-zinc-500 tracking-wide no-underline hover:text-zinc-600 absolute top-1 right-0`}
                        >
                            Forgot Password?
                        </Link>
                    </div>

                    <Captcha
                        className='mt-6'
                        onError={(error) => {
                            console.error('Captcha error:', error);
                            clearAndAddHttpError({
                                error: new Error('Captcha verification failed. Please try again.'),
                            });
                        }}
                    />

                    <div className={`mt-6`}>
                        <Button
                            className={`relative mt-4 w-full rounded-full bg-brand border-0 ring-0 outline-hidden capitalize font-bold text-sm py-2 hover:cursor-pointer`}
                            type={'submit'}
                            size={'xlarge'}
                            isLoading={isSubmitting}
                            disabled={isSubmitting}
                        >
                            Login
                        </Button>
                    </div>

                    <OAuthProviders />
                </LoginFormContainer>
            )}
        </Formik>
    );
}

export default LoginContainer;
