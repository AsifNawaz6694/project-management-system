import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { AlertTriangle, type LucideIcon } from 'lucide-react';
import { useState, type ReactNode } from 'react';

type Tone = 'destructive' | 'warning' | 'info';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: Tone;
    icon?: LucideIcon;
    onConfirm: () => void | Promise<void>;
    loading?: boolean;
}

const TONE_STYLES: Record<Tone, { iconWrap: string; confirmClass: string }> = {
    destructive: {
        iconWrap: 'from-rose-500 to-pink-600',
        confirmClass: 'from-rose-500 to-pink-600 bg-gradient-to-br text-white hover:from-rose-600 hover:to-pink-700',
    },
    warning: {
        iconWrap: 'from-amber-500 to-orange-600',
        confirmClass: 'from-amber-500 to-orange-600 bg-gradient-to-br text-white',
    },
    info: {
        iconWrap: 'from-violet-500 to-indigo-600',
        confirmClass: 'from-violet-600 to-indigo-600 bg-gradient-to-br text-white',
    },
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    tone = 'destructive',
    icon: Icon = AlertTriangle,
    onConfirm,
    loading = false,
}: ConfirmDialogProps) {
    const t = TONE_STYLES[tone];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md rounded-2xl">
                <div className="flex items-start gap-4">
                    <div className={cn('flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br shadow-soft-md ring-1 ring-white/30', t.iconWrap)}>
                        <Icon className="size-5 text-white" />
                    </div>
                    <DialogHeader className="space-y-1.5">
                        <DialogTitle className="font-display text-lg font-bold tracking-tight">{title}</DialogTitle>
                        {description && (
                            <DialogDescription className="text-sm leading-relaxed">{description}</DialogDescription>
                        )}
                    </DialogHeader>
                </div>
                <DialogFooter className="gap-2 sm:gap-2">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={loading}>
                        {cancelLabel}
                    </Button>
                    <Button
                        onClick={async () => {
                            await onConfirm();
                        }}
                        disabled={loading}
                        className={cn('gap-2', t.confirmClass)}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function useConfirm() {
    const [open, setOpen] = useState(false);
    return { open, setOpen, openConfirm: () => setOpen(true), closeConfirm: () => setOpen(false) };
}
