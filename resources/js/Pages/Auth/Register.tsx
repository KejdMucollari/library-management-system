import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Register" />

            <div className="library-shell relative min-h-screen">
                <div className="library-bg-fixed" aria-hidden />
                <div className="library-overlay-fixed" aria-hidden />

                <div className="library-content flex min-h-screen items-center justify-center px-4 py-10">
                    <div className="w-full max-w-[420px]">
                        <div className="library-glass-panel p-8 opacity-0 shadow-2xl transition duration-300 ease-out animate-[loginCardIn_260ms_ease-out_forwards]">
                            <div className="text-center">
                                <h1 className="font-display text-2xl font-semibold tracking-tight text-[#f5f0e8]">
                                    Create your account
                                </h1>
                                <p className="mt-1 text-sm text-[rgba(245,240,232,0.7)]">
                                    Start building your personal library
                                </p>
                            </div>

                            <form onSubmit={submit} className="mt-6 space-y-4">
                                <div>
                                    <InputLabel htmlFor="name" value="Name" />
                                    <TextInput
                                        id="name"
                                        name="name"
                                        value={data.name}
                                        className="mt-1 block w-full"
                                        autoComplete="name"
                                        isFocused={true}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="email" value="Email" />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        className="mt-1 block w-full"
                                        autoComplete="username"
                                        onChange={(e) => setData('email', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.email} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="password" value="Password" />

                                    <div className="relative mt-1">
                                        <TextInput
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            className="block w-full pr-12"
                                            autoComplete="new-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            required
                                        />

                                        <button
                                            type="button"
                                            className="absolute inset-y-0 right-0 flex items-center px-3 text-sm text-[rgba(245,240,232,0.55)] hover:text-[#f5f0e8]"
                                            onClick={() => setShowPassword((v) => !v)}
                                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                                        >
                                            {showPassword ? (
                                                <svg
                                                    className="h-5 w-5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                    />
                                                    <path
                                                        d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                    />
                                                </svg>
                                            ) : (
                                                <svg
                                                    className="h-5 w-5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        d="M4 4l16 16"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M10.6 10.6a2 2 0 0 0 2.8 2.8"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M6.5 6.8C4.4 8.4 3 10.7 3 12c0 0 3.5 7 9 7 1.7 0 3.2-.6 4.5-1.4"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M9.7 5.4C10.4 5.2 11.2 5 12 5c5.5 0 9 7 9 7 0 .9-.7 2.3-1.8 3.6"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                </svg>
                                            )}
                                        </button>
                                    </div>

                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="password_confirmation"
                                        value="Confirm Password"
                                    />

                                    <div className="relative mt-1">
                                        <TextInput
                                            id="password_confirmation"
                                            type={showConfirm ? 'text' : 'password'}
                                            name="password_confirmation"
                                            value={data.password_confirmation}
                                            className="block w-full pr-12"
                                            autoComplete="new-password"
                                            onChange={(e) =>
                                                setData('password_confirmation', e.target.value)
                                            }
                                            required
                                        />

                                        <button
                                            type="button"
                                            className="absolute inset-y-0 right-0 flex items-center px-3 text-sm text-[rgba(245,240,232,0.55)] hover:text-[#f5f0e8]"
                                            onClick={() => setShowConfirm((v) => !v)}
                                            aria-label={
                                                showConfirm
                                                    ? 'Hide password confirmation'
                                                    : 'Show password confirmation'
                                            }
                                        >
                                            {showConfirm ? (
                                                <svg
                                                    className="h-5 w-5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        d="M3 12s3.5-7 9-7 9 7 9 7-3.5 7-9 7-9-7-9-7Z"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                    />
                                                    <path
                                                        d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                    />
                                                </svg>
                                            ) : (
                                                <svg
                                                    className="h-5 w-5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        d="M4 4l16 16"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M10.6 10.6a2 2 0 0 0 2.8 2.8"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M6.5 6.8C4.4 8.4 3 10.7 3 12c0 0 3.5 7 9 7 1.7 0 3.2-.6 4.5-1.4"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                    <path
                                                        d="M9.7 5.4C10.4 5.2 11.2 5 12 5c5.5 0 9 7 9 7 0 .9-.7 2.3-1.8 3.6"
                                                        stroke="currentColor"
                                                        strokeWidth="1.6"
                                                        strokeLinecap="round"
                                                    />
                                                </svg>
                                            )}
                                        </button>
                                    </div>

                                    <InputError
                                        message={errors.password_confirmation}
                                        className="mt-2"
                                    />
                                </div>

                                <PrimaryButton
                                    className="w-full justify-center py-3 text-sm normal-case tracking-normal"
                                    disabled={processing}
                                >
                                    Register
                                </PrimaryButton>

                                <p className="text-center text-sm text-[rgba(245,240,232,0.7)]">
                                    Already have an account?{' '}
                                    <Link
                                        href={route('login')}
                                        className="font-medium text-[#c9a84c] hover:text-[#d4b76a]"
                                    >
                                        Login
                                    </Link>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
