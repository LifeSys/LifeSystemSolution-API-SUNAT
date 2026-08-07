import { Head, router } from '@inertiajs/react';
import {
    ActivitySquare,
    AlertTriangle,
    Bug,
    CheckCircle2,
    Clock,
    DatabaseZap,
    FileText,
    RefreshCcw,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type LogEntry = {
    datetime: string | null;
    source: 'sunat' | 'laravel' | string;
    level: string;
    message: string;
    context: Record<string, unknown> | null;
    file: string;
    raw: string;
};

type Filters = {
    source: string;
    level: string;
    q: string;
};

type Props = {
    entries: LogEntry[];
    filters: Filters;
    stats: {
        sunat_files: number;
        laravel_files: number;
        max_entries: number;
    };
    sources: { value: string; label: string }[];
    levels: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Logs API/SUNAT', href: '/admin/logs' },
];

const levelTone: Record<string, string> = {
    DEBUG: 'bg-slate-100 text-slate-700 dark:bg-slate-900 dark:text-slate-300',
    INFO: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    NOTICE: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300',
    WARNING:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    ERROR: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    CRITICAL: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ALERT: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    EMERGENCY: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

const contextValue = (value: unknown) => {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }

    return String(value);
};

function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: number | string;
    icon: typeof ActivitySquare;
}) {
    return (
        <Card className="flex items-center gap-3 p-4">
            <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Icon className="size-5" />
            </div>
            <div>
                <div className="text-2xl leading-none font-semibold">
                    {value}
                </div>
                <div className="text-xs text-muted-foreground">{label}</div>
            </div>
        </Card>
    );
}

function ContextTable({ context }: { context: Record<string, unknown> }) {
    return (
        <div className="mt-3 overflow-hidden rounded-lg border">
            <div className="grid grid-cols-1 divide-y text-sm md:grid-cols-2 md:divide-x md:divide-y-0">
                {Object.entries(context).map(([key, value]) => (
                    <div
                        key={key}
                        className="grid grid-cols-[150px_1fr] gap-2 p-3"
                    >
                        <div className="font-medium text-muted-foreground">
                            {key}
                        </div>
                        <pre className="font-mono text-xs leading-relaxed break-words whitespace-pre-wrap">
                            {contextValue(value)}
                        </pre>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function LogsIndex({
    entries,
    filters,
    stats,
    sources,
    levels,
}: Props) {
    const [source, setSource] = useState(filters.source || 'all');
    const [level, setLevel] = useState(filters.level || '');
    const [q, setQ] = useState(filters.q || '');

    const counts = useMemo(() => {
        const errors = entries.filter((entry) =>
            ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'].includes(entry.level),
        ).length;
        const sunat = entries.filter(
            (entry) => entry.source === 'sunat',
        ).length;

        return { errors, sunat };
    }, [entries]);

    const applyFilters = (event?: FormEvent) => {
        event?.preventDefault();

        router.get(
            '/admin/logs',
            { source, level, q },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSource('all');
        setLevel('');
        setQ('');
        router.get(
            '/admin/logs',
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Logs API/SUNAT" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ActivitySquare className="size-5" />
                        </div>
                        <div>
                            <h1 className="text-xl font-semibold tracking-tight">
                                Logs API/SUNAT
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Revisa errores, endpoints destino, datos
                                enviados de forma segura y respuestas recibidas
                                del API.
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.reload({ only: ['entries', 'stats'] })
                        }
                    >
                        <RefreshCcw className="mr-2 size-4" />
                        Actualizar
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Eventos mostrados"
                        value={entries.length}
                        icon={FileText}
                    />
                    <StatCard
                        label="Eventos SUNAT"
                        value={counts.sunat}
                        icon={DatabaseZap}
                    />
                    <StatCard
                        label="Errores visibles"
                        value={counts.errors}
                        icon={AlertTriangle}
                    />
                    <StatCard
                        label="Límite de pantalla"
                        value={stats.max_entries}
                        icon={Clock}
                    />
                </div>

                <Card className="p-4">
                    <form
                        onSubmit={applyFilters}
                        className="grid gap-3 md:grid-cols-[180px_180px_1fr_auto_auto] md:items-end"
                    >
                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">Origen</span>
                            <select
                                value={source}
                                onChange={(event) =>
                                    setSource(event.target.value)
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {sources.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">Nivel</span>
                            <select
                                value={level}
                                onChange={(event) =>
                                    setLevel(event.target.value)
                                }
                                className="h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <option value="">Todos</option>
                                {levels.map((item) => (
                                    <option key={item} value={item}>
                                        {item}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="grid gap-1.5 text-sm">
                            <span className="font-medium">Buscar</span>
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={q}
                                    onChange={(event) =>
                                        setQ(event.target.value)
                                    }
                                    className="pl-9"
                                    placeholder="endpoint, RUC, documento, código, error..."
                                />
                            </div>
                        </label>

                        <Button type="submit">Filtrar</Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            Limpiar
                        </Button>
                    </form>
                </Card>

                <div className="space-y-3">
                    {entries.length === 0 && (
                        <Card className="p-8 text-center">
                            <CheckCircle2 className="mx-auto mb-3 size-8 text-muted-foreground" />
                            <h2 className="font-semibold">
                                No hay eventos para los filtros seleccionados
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Cuando se envíen comprobantes o se registren
                                errores, aparecerán aquí.
                            </p>
                        </Card>
                    )}

                    {entries.map((entry, index) => (
                        <Card
                            key={`${entry.file}-${entry.datetime}-${index}`}
                            className="p-4"
                        >
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div className="min-w-0 flex-1">
                                    <div className="mb-2 flex flex-wrap items-center gap-2">
                                        <Badge
                                            className={
                                                levelTone[entry.level] ??
                                                levelTone.INFO
                                            }
                                        >
                                            {entry.level}
                                        </Badge>
                                        <Badge variant="outline">
                                            {entry.source === 'sunat'
                                                ? 'SUNAT'
                                                : 'Sistema'}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {entry.datetime ?? 'Sin fecha'}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {entry.file}
                                        </span>
                                    </div>
                                    <p className="text-sm font-medium break-words">
                                        {entry.message}
                                    </p>

                                    {entry.context && (
                                        <ContextTable context={entry.context} />
                                    )}
                                </div>

                                {[
                                    'ERROR',
                                    'CRITICAL',
                                    'ALERT',
                                    'EMERGENCY',
                                ].includes(entry.level) && (
                                    <div className="text-red-600 dark:text-red-400">
                                        <Bug className="size-5" />
                                    </div>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
