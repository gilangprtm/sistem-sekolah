import { Link, Head } from '@inertiajs/react';
import { Command } from 'lucide-react';
import { GoogleButton } from '@/components/auth/google-button';
import { LoginForm } from '@/components/auth/login-form';

export default function LoginV1() {
    return (
        <>
            <Head title="Login v1" />
            <div className="flex h-dvh">
                <div className="hidden bg-primary lg:block lg:w-1/3">
                    <div className="flex h-full flex-col items-center justify-center p-12 text-center">
                        <div className="space-y-6">
                            <Command className="mx-auto size-12 text-primary-foreground" />
                            <div className="space-y-2">
                                <h1 className="text-5xl font-light text-primary-foreground">
                                    Hello again
                                </h1>
                                <p className="text-xl text-primary-foreground/80">
                                    Login to continue
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex w-full items-center justify-center bg-background p-8 lg:w-2/3">
                    <div className="w-full max-w-md space-y-10 py-24 lg:py-32">
                        <div className="space-y-4 text-center">
                            <div className="font-medium tracking-tight">Login</div>
                            <div className="mx-auto max-w-xl text-muted-foreground">
                                Welcome back. Enter your email and password, let&apos;s hope you
                                remember them this time.
                            </div>
                        </div>
                        <div className="space-y-4">
                            <LoginForm />
                            <GoogleButton className="w-full" variant="outline" />
                            <p className="text-center text-xs text-muted-foreground">
                                Don&apos;t have an account?{' '}
                                <Link href="/auth/v1/register" className="text-primary">
                                    Register
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
