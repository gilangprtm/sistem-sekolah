import { Link, Head } from '@inertiajs/react';
import { Globe, Command } from 'lucide-react';
import { GoogleButton } from '@/components/auth/google-button';
import { RegisterForm } from '@/components/auth/register-form';
import { Separator } from '@/components/ui/separator';
import { APP_CONFIG } from '@/config/app-config';

export default function RegisterV2() {
    return (
        <>
            <Head title="Register v2" />
            <div className="grid h-dvh justify-center p-2 lg:grid-cols-2">
                <div className="relative order-2 hidden h-full rounded-3xl bg-primary lg:flex">
                    <div className="absolute top-10 space-y-1 px-10 text-primary-foreground">
                        <Command className="size-10" />
                        <h1 className="text-2xl font-medium">{APP_CONFIG.name}</h1>
                        <p className="text-sm">Design. Build. Launch. Repeat.</p>
                    </div>

                    <div className="absolute bottom-10 flex w-full justify-between px-10">
                        <div className="flex-1 space-y-1 text-primary-foreground">
                            <h2 className="font-medium">Ready to launch?</h2>
                            <p className="text-sm">
                                Clone the repo, install dependencies, and your dashboard is live in
                                minutes.
                            </p>
                        </div>
                        <Separator orientation="vertical" className="mx-3 h-auto!" />
                        <div className="flex-1 space-y-1 text-primary-foreground">
                            <h2 className="font-medium">Need help?</h2>
                            <p className="text-sm">
                                Check out the docs or open an issue on GitHub, community support is
                                just a click away.
                            </p>
                        </div>
                    </div>
                </div>
                <div className="relative order-1 flex h-full">
                    <div className="mx-auto flex w-full flex-col justify-center space-y-8 sm:w-[350px]">
                        <div className="space-y-2 text-center">
                            <h1 className="text-3xl font-medium">Create your account</h1>
                            <p className="text-sm text-muted-foreground">
                                Please enter your details to register.
                            </p>
                        </div>
                        <div className="space-y-4">
                            <GoogleButton className="w-full" />
                            <div className="relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t after:border-border">
                                <span className="relative z-10 bg-background px-2 text-muted-foreground">
                                    Or continue with
                                </span>
                            </div>
                            <RegisterForm />
                        </div>
                    </div>

                    <div className="absolute top-5 flex w-full justify-end px-10">
                        <div className="text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <Link href="/auth/v2/login" className="text-foreground">
                                Login
                            </Link>
                        </div>
                    </div>

                    <div className="absolute bottom-5 flex w-full justify-between px-10">
                        <div className="text-sm">{APP_CONFIG.copyright}</div>
                        <div className="flex items-center gap-1 text-sm">
                            <Globe className="size-4 text-muted-foreground" />
                            ENG
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
