import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, ShieldCheck } from 'lucide-react';
import { type FormEventHandler } from 'react';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <AuthLayout
            title="Welcome back"
            description="Sign in to continue. We'll send a verification code to your email to confirm it's you."
        >
            <Head title="Sign in" />

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="space-y-1.5">
                    <Label htmlFor="email" className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.14em]">
                        Work email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@company.com"
                        className="h-12"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password" className="text-muted-foreground text-[11px] font-bold uppercase tracking-[0.14em]">
                            Password
                        </Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="text-xs">
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                        className="h-12"
                    />
                    <InputError message={errors.password} />
                </div>

                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="size-4 accent-violet-600"
                    />
                    <span className="text-muted-foreground">Keep me signed in on this device</span>
                </label>

                <Button type="submit" disabled={processing} size="lg" className="w-full gap-2">
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    Continue
                </Button>

                <div className="text-muted-foreground flex items-center justify-center gap-1.5 text-xs">
                    <ShieldCheck className="size-3.5" />
                    Protected with 2-step verification
                </div>
            </form>

            {status && <div className="mt-3 text-center text-sm font-medium text-emerald-600">{status}</div>}
        </AuthLayout>
    );
}
