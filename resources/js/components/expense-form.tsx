import InputError from '@/components/input-error';
import { SoftCard, SoftCardBody, SoftCardTitle } from '@/components/soft-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EXPENSE_CATEGORY_META, type ExpenseCategory } from '@/lib/expenses';
import { CURRENCY_META, SUPPORTED_CURRENCIES } from '@/lib/currency';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Paperclip, X } from 'lucide-react';
import { type FormEventHandler, useRef, useState } from 'react';

interface ExpenseFormState {
    project_id: number | null;
    title: string;
    description: string;
    amount: string;
    currency: string;
    category: ExpenseCategory;
    expense_date: string;
    receipt: File | null;
}

interface ProjectOption {
    id: number;
    slug: string;
    title: string;
    color: string;
}

interface ExpenseFormProps {
    initial: ExpenseFormState;
    projects: ProjectOption[];
    categories: string[];
    currencies?: string[];
    submitUrl: string;
    submitMethod: 'post' | 'patch';
    submitLabel: string;
    cancelUrl: string;
    existingReceiptName?: string | null;
}

export function ExpenseForm({ initial, projects, categories, currencies, submitUrl, submitMethod, submitLabel, cancelUrl, existingReceiptName }: ExpenseFormProps) {
    const currencyList = (currencies && currencies.length > 0 ? currencies : SUPPORTED_CURRENCIES) as string[];
    const fileRef = useRef<HTMLInputElement>(null);
    const [receiptName, setReceiptName] = useState<string | null>(existingReceiptName ?? null);
    const { data, setData, post, processing, errors, transform } = useForm<ExpenseFormState>({ ...initial });

    transform((d) => {
        const out: Record<string, unknown> = { ...d };
        if (submitMethod === 'patch' && d.receipt) {
            out._method = 'patch';
        }
        return out;
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitMethod === 'patch' && !data.receipt) {
            // No file → standard PATCH
            post(submitUrl, { method: 'patch' as 'post', preserveScroll: true });
            return;
        }
        post(submitUrl, { forceFormData: true, preserveScroll: true });
    };

    return (
        <form onSubmit={submit} encType="multipart/form-data" className="space-y-5">
            <SoftCard>
                <SoftCardTitle eyebrow="Expense">Details</SoftCardTitle>
                <SoftCardBody className="grid gap-4 md:grid-cols-2">
                    <Field label="Project" error={errors.project_id} className="md:col-span-2">
                        <Select value={data.project_id ? String(data.project_id) : ''} onValueChange={(v) => setData('project_id', Number(v))}>
                            <SelectTrigger className="h-11 rounded-xl"><SelectValue placeholder="Pick a project" /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {projects.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.title}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Title" error={errors.title} className="md:col-span-2">
                        <Input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="e.g. Annual Figma plan" required autoFocus />
                    </Field>
                    <Field label="Amount" error={errors.amount}>
                        <div className="flex gap-2">
                            <Select value={data.currency} onValueChange={(v) => setData('currency', v)}>
                                <SelectTrigger className="h-11 w-[120px] rounded-xl"><SelectValue /></SelectTrigger>
                                <SelectContent className="rounded-xl">
                                    {currencyList.map((c) => (
                                        <SelectItem key={c} value={c}>
                                            {c} · {CURRENCY_META[c as keyof typeof CURRENCY_META]?.label ?? c}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input type="number" step="0.01" min="0" value={data.amount} onChange={(e) => setData('amount', e.target.value)} placeholder="0.00" required className="flex-1" />
                        </div>
                    </Field>
                    <Field label="Date" error={errors.expense_date}>
                        <Input type="date" value={data.expense_date} onChange={(e) => setData('expense_date', e.target.value)} required />
                    </Field>
                    <Field label="Category" error={errors.category}>
                        <Select value={data.category} onValueChange={(v) => setData('category', v as ExpenseCategory)}>
                            <SelectTrigger className="h-11 rounded-xl"><SelectValue /></SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {categories.map((c) => <SelectItem key={c} value={c}>{EXPENSE_CATEGORY_META[c as ExpenseCategory]?.label ?? c}</SelectItem>)}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Receipt (PDF / image)" error={errors.receipt}>
                        <div className="flex items-center gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                className="hidden"
                                accept="image/*,application/pdf"
                                onChange={(e) => {
                                    const f = e.target.files?.[0] ?? null;
                                    setData('receipt', f);
                                    setReceiptName(f?.name ?? null);
                                }}
                            />
                            <Button type="button" variant="soft" size="sm" className="gap-1.5" onClick={() => fileRef.current?.click()}>
                                <Paperclip className="size-3.5" /> {receiptName ? 'Replace' : 'Attach'}
                            </Button>
                            {receiptName && (
                                <span className="bg-muted/50 ring-border/60 ring-1 inline-flex max-w-[240px] items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px]">
                                    <span className="truncate">{receiptName}</span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setData('receipt', null);
                                            setReceiptName(null);
                                            if (fileRef.current) fileRef.current.value = '';
                                        }}
                                        className="text-muted-foreground hover:text-rose-600"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </span>
                            )}
                        </div>
                    </Field>
                    <Field label="Description" error={errors.description} className="md:col-span-2">
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            rows={3}
                            className="bg-card shadow-soft-xs ring-border focus-visible:border-foreground/30 focus-visible:ring-violet-200/60 dark:focus-visible:ring-violet-500/20 hover:border-foreground/20 ring-1 w-full rounded-xl px-3.5 py-2.5 text-sm transition-all focus-visible:outline-none focus-visible:ring-4"
                            placeholder="Add context about what this expense is for…"
                        />
                    </Field>
                </SoftCardBody>
            </SoftCard>

            <div className="flex items-center justify-end gap-2">
                <Button asChild variant="ghost"><Link href={cancelUrl}>Cancel</Link></Button>
                <Button type="submit" disabled={processing} className="gap-2">
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

function Field({ label, error, children, className }: { label: string; error?: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-muted-foreground text-[10px] font-bold uppercase tracking-[0.14em]">{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
