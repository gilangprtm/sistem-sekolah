import { useShallow } from 'zustand/react/shallow';

import type { ThemeMode } from '@/lib/preferences/theme';
import { usePreferencesStore } from '@/stores/preferences/preferences-provider';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ThemeMode;

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

export function useAppearance(): UseAppearanceReturn {
    const { appearance, resolvedAppearance, setPreference } =
        usePreferencesStore(
            useShallow((state) => ({
                appearance: state.values.theme_mode,
                resolvedAppearance: state.resolvedThemeMode,
                setPreference: state.setPreference,
            })),
        );

    const updateAppearance = (mode: Appearance): void => {
        setPreference('theme_mode', mode);
    };

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
