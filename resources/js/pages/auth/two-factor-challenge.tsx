import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, MailCheck, RotateCcw } from 'lucide-react';
import { type ClipboardEvent, type FormEventHandler, type KeyboardEvent, useEffect, useRef, useState } from 'react';

type ChallengeForm = {
    code: string;
};

interface ChallengeProps {
    email?: string;
    status?: string;
    cooldown: number;
}

const LENGTH = 6;

export default function TwoFactorChallenge({ email, status, cooldown }: ChallengeProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ChallengeForm>({ code: '' });
    const [digits, setDigits] = useState<string[]>(Array(LENGTH).fill(''));
    const refs = useRef<Array<HTMLInputElement | null>>([]);
    const [cooldownLeft, setCooldownLeft] = useState(0);

    useEffect(() => {
        refs.current[0]?.focus();
    }, []);

    useEffect(() => {
        if (cooldownLeft <= 0) return;
        const t = setTimeout(() => setCooldownLeft((s) => s - 1), 1000);
        return () => clearTimeout(t);
    }, [cooldownLeft]);

    useEffect(() => {
        setData('code', digits.join(''));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [digits]);

    const handleChange = (index: number, value: string) => {
        const digit = value.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[index] = digit;
        setDigits(next);
        if (digit && index < LENGTH - 1) refs.current[index + 1]?.focus();
    };

    const handleKey = (index: number, e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Backspace' && !digits[index] && index > 0) refs.current[index - 1]?.focus();
    };

    const handlePaste = (e: ClipboardEvent<HTMLInputElement>) => {
        const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, LENGTH);
        if (!pasted) return;
        e.preventDefault();
        const next = pasted.padEnd(LENGTH, '').split('').slice(0, LENGTH);
        setDigits(next);
        const focusIdx = Math.min(pasted.length, LENGTH - 1);
        refs.current[focusIdx]?.focus();
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.verify'), {
            onError: () => {
                setDigits(Array(LENGTH).fill(''));
                reset('code');
                refs.current[0]?.focus();
            },
        });
    };

    const resend = () => {
        if (cooldownLeft > 0) return;
        router.post(route('two-factor.resend'), {}, { preserveScroll: true });
        setCooldownLeft(cooldown);
    };

    return (
        <AuthLayout
            title="Check your email"
            description={
                email
                    ? `We sent a 6-digit code to ${maskEmail(email)}. Enter it below to finish signing in.`
                    : 'Enter the 6-digit code we just sent to your email.'
            }
        >
            <Head title="Verify your identity" />

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="flex justify-center gap-2" onPaste={handlePaste}>
                    {digits.map((digit, i) => (
                        <input
                            key={i}
                            ref={(el) => {
                                refs.current[i] = el;
                            }}
                            inputMode="numeric"
                            maxLength={1}
                            value={digit}
                            onChange={(e) => handleChange(i, e.target.value)}
                            onKeyDown={(e) => handleKey(i, e)}
                            className="bg-card ring-border/70 shadow-soft-xs size-13 rounded-xl text-center text-2xl font-bold tabular-nums ring-1 transition-all focus-visible:border-blue-500 focus-visible:ring-4 focus-visible:ring-blue-500/40 focus-visible:outline-none sm:size-14"
                        />
                    ))}
                </div>
                <InputError message={errors.code} className="text-center" />

                <Button type="submit" disabled={processing || data.code.length < LENGTH} size="lg" className="w-full gap-2">
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    Verify and continue
                </Button>

                <div className="flex flex-col items-center gap-2 text-xs">
                    <button
                        type="button"
                        onClick={resend}
                        disabled={cooldownLeft > 0}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <RotateCcw className="size-3.5" />
                        {cooldownLeft > 0 ? `Resend in ${cooldownLeft}s` : 'Resend the code'}
                    </button>
                    <a href={route('login')} className="text-muted-foreground hover:text-foreground transition-colors">
                        Use a different account
                    </a>
                </div>
            </form>

            {status && (
                <div className="mt-3 flex items-center justify-center gap-2 text-sm font-medium text-emerald-600">
                    <MailCheck className="size-4" /> {status}
                </div>
            )}
        </AuthLayout>
    );
}

function maskEmail(email: string): string {
    const [local, domain] = email.split('@');
    if (!domain) return email;
    if (local.length <= 2) return `${local[0]}*@${domain}`;
    return `${local[0]}${'*'.repeat(Math.min(local.length - 2, 6))}${local.slice(-1)}@${domain}`;
}
