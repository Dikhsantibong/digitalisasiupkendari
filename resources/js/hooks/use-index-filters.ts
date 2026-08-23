import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export type FilterValues = Record<string, string | undefined>;

/**
 * Keeps list filters in the URL so a filtered table can be shared and reloaded.
 *
 * Text input is debounced; select changes apply immediately.
 */
export function useIndexFilters<T extends FilterValues>(
    url: string,
    initial: T,
) {
    const [values, setValues] = useState<T>(initial);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const isFirstRender = useRef(true);

    useEffect(() => {
        return () => {
            if (debounce.current) {
                clearTimeout(debounce.current);
            }
        };
    }, []);

    const visit = (next: T) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== undefined && value !== '',
            ),
        );

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    /** Apply immediately — for selects and toggles. */
    const setFilter = (key: keyof T, value: string | undefined) => {
        const next = { ...values, [key]: value } as T;
        setValues(next);
        visit(next);
    };

    /** Apply after the user stops typing. */
    const setSearch = (key: keyof T, value: string) => {
        const next = { ...values, [key]: value } as T;
        setValues(next);
        isFirstRender.current = false;

        if (debounce.current) {
            clearTimeout(debounce.current);
        }

        debounce.current = setTimeout(() => visit(next), 350);
    };

    const reset = () => {
        const cleared = Object.fromEntries(
            Object.keys(values).map((key) => [key, undefined]),
        ) as T;

        setValues(cleared);
        visit(cleared);
    };

    const isFiltered = Object.values(values).some(
        (value) => value !== undefined && value !== '',
    );

    return { values, setFilter, setSearch, reset, isFiltered };
}
