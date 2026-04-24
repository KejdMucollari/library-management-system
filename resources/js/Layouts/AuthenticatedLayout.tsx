import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    return (
        <div className="library-shell min-h-screen">
            <div className="library-bg-fixed" aria-hidden />
            <div className="library-overlay-fixed" aria-hidden />
            <div className="library-content">
                <nav className="relative z-50 border-b border-white/[0.08] bg-[rgba(10,6,3,0.4)] backdrop-blur-[12px]">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 justify-between">
                            <div className="flex">
                                <div className="flex shrink-0 items-center">
                                    <Link href="/">
                                        <ApplicationLogo className="block h-11 w-auto fill-current text-[#c9a84c]" />
                                    </Link>
                                </div>

                                <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                    {!user.is_admin ? (
                                        <NavLink
                                            href={route('books.index')}
                                            active={route().current('books.*')}
                                        >
                                            My Library
                                        </NavLink>
                                    ) : (
                                        <>
                                            <NavLink
                                                href={route('admin.dashboard')}
                                                active={route().current(
                                                    'admin.dashboard',
                                                )}
                                            >
                                                Dashboard
                                            </NavLink>
                                            <NavLink
                                                href={route('admin.books.index')}
                                                active={route().current(
                                                    'admin.books.*',
                                                )}
                                            >
                                                All Books
                                            </NavLink>
                                            <NavLink
                                                href={route('admin.users.index')}
                                                active={route().current(
                                                    'admin.users.*',
                                                )}
                                            >
                                                Users
                                            </NavLink>
                                        </>
                                    )}
                                </div>
                            </div>

                            <div className="hidden sm:ms-6 sm:flex sm:items-center">
                                <div className="relative ms-3">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <span className="inline-flex rounded-md">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center rounded-md border border-white/10 bg-black/20 px-3 py-2 text-sm font-medium text-[#f5f0e8] backdrop-blur-md transition duration-150 ease-in-out hover:border-white/20 hover:bg-black/30 focus:outline-none"
                                                >
                                                    {user.name}

                                                    <svg
                                                        className="-me-0.5 ms-2 h-4 w-4 text-[rgba(245,240,232,0.65)]"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </span>
                                        </Dropdown.Trigger>

                                        <Dropdown.Content>
                                            <Dropdown.Link
                                                href={route('profile.edit')}
                                            >
                                                Profile
                                            </Dropdown.Link>
                                            <Dropdown.Link
                                                href={route('logout')}
                                                method="post"
                                                as="button"
                                            >
                                                Log Out
                                            </Dropdown.Link>
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            </div>

                            <div className="-me-2 flex items-center sm:hidden">
                                <button
                                    onClick={() =>
                                        setShowingNavigationDropdown(
                                            (previousState) => !previousState,
                                        )
                                    }
                                    className="inline-flex items-center justify-center rounded-md p-2 text-[rgba(245,240,232,0.7)] transition duration-150 ease-in-out hover:bg-white/10 hover:text-[#f5f0e8] focus:bg-white/10 focus:text-[#f5f0e8] focus:outline-none"
                                >
                                    <svg
                                        className="h-6 w-6"
                                        stroke="currentColor"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            className={
                                                !showingNavigationDropdown
                                                    ? 'inline-flex'
                                                    : 'hidden'
                                            }
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                        <path
                                            className={
                                                showingNavigationDropdown
                                                    ? 'inline-flex'
                                                    : 'hidden'
                                            }
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        className={
                            (showingNavigationDropdown ? 'block' : 'hidden') +
                            ' border-t border-white/10 bg-[rgba(10,6,3,0.55)] backdrop-blur-xl sm:hidden'
                        }
                    >
                        <div className="space-y-1 pb-3 pt-2">
                            {!user.is_admin ? (
                                <ResponsiveNavLink
                                    href={route('books.index')}
                                    active={route().current('books.*')}
                                >
                                    My Library
                                </ResponsiveNavLink>
                            ) : (
                                <>
                                    <ResponsiveNavLink
                                        href={route('admin.dashboard')}
                                        active={route().current('admin.dashboard')}
                                    >
                                        Dashboard
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink
                                        href={route('admin.books.index')}
                                        active={route().current('admin.books.*')}
                                    >
                                        All Books
                                    </ResponsiveNavLink>
                                    <ResponsiveNavLink
                                        href={route('admin.users.index')}
                                        active={route().current('admin.users.*')}
                                    >
                                        Users
                                    </ResponsiveNavLink>
                                </>
                            )}
                        </div>

                        <div className="border-t border-white/10 pb-1 pt-4">
                            <div className="px-4">
                                <div className="text-base font-medium text-[#f5f0e8]">
                                    {user.name}
                                </div>
                                <div className="text-sm text-[rgba(245,240,232,0.55)]">
                                    {user.email}
                                </div>
                            </div>

                            <div className="mt-3 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>
                                    Profile
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    method="post"
                                    href={route('logout')}
                                    as="button"
                                >
                                    Log Out
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                {header && (
                    <header className="relative z-0 border-b border-white/10 bg-[rgba(10,6,3,0.35)] backdrop-blur-xl">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main className="relative z-0">{children}</main>
            </div>
        </div>
    );
}
