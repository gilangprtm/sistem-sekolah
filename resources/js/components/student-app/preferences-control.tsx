import { Settings } from 'lucide-react';
import { useShallow } from 'zustand/react/shallow';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fontOptions } from '@/lib/fonts/registry';
import type { FontKey } from '@/lib/fonts/registry';
import { THEME_PRESET_OPTIONS } from '@/lib/preferences/theme';
import type { ThemeMode, ThemePreset } from '@/lib/preferences/theme';
import { usePreferencesStore } from '@/stores/preferences/preferences-provider';

export default function StudentPreferencesControl() {
    const { values, resolvedThemeMode, setPreference } = usePreferencesStore(
        useShallow((state) => ({
            values: state.values,
            resolvedThemeMode: state.resolvedThemeMode,
            setPreference: state.setPreference,
        })),
    );

    const updateThemeMode = (value: string) => {
        if (value === 'light' || value === 'dark' || value === 'system') {
            setPreference('theme_mode', value satisfies ThemeMode);
        }
    };

    const updateThemePreset = (value: string) => {
        if (THEME_PRESET_OPTIONS.some((preset) => preset.value === value)) {
            setPreference('theme_preset', value as ThemePreset);
        }
    };

    const updateFont = (value: string) => {
        if (fontOptions.some((font) => font.key === value)) {
            setPreference('font', value as FontKey);
        }
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Preferensi tampilan"
                    title="Preferensi tampilan"
                >
                    <Settings aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <ModalShell className="gap-3 p-3 [&_[data-slot=dialog-close]]:min-h-11 [&_[data-slot=dialog-close]]:min-w-11">
                <DialogHeader className="gap-1">
                    <DialogTitle className="text-sm">
                        Preferensi tampilan
                    </DialogTitle>
                    <DialogDescription className="text-xs">
                        Atur tema, font, dan mode warna aplikasi.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    <div className="space-y-1">
                        <Label
                            htmlFor="student-theme-preset"
                            className="text-xs"
                        >
                            Theme Preset
                        </Label>
                        <Select
                            value={values.theme_preset}
                            onValueChange={updateThemePreset}
                        >
                            <SelectTrigger
                                id="student-theme-preset"
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih tema" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {THEME_PRESET_OPTIONS.map((preset) => (
                                        <SelectItem
                                            key={preset.value}
                                            value={preset.value}
                                        >
                                            <span
                                                className="size-2.5 rounded-full"
                                                style={{
                                                    backgroundColor:
                                                        resolvedThemeMode ===
                                                        'dark'
                                                            ? preset.primary
                                                                  .dark
                                                            : preset.primary
                                                                  .light,
                                                }}
                                            />
                                            {preset.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="student-font" className="text-xs">
                            Fonts
                        </Label>
                        <Select value={values.font} onValueChange={updateFont}>
                            <SelectTrigger id="student-font" className="w-full">
                                <SelectValue placeholder="Pilih font" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {fontOptions.map((font) => (
                                        <SelectItem
                                            key={font.key}
                                            value={font.key}
                                        >
                                            {font.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Theme Mode</Label>
                        <ToggleGroup
                            size="sm"
                            type="single"
                            variant="outline"
                            spacing={0}
                            value={values.theme_mode}
                            onValueChange={updateThemeMode}
                            aria-label="Theme Mode"
                            className="w-full"
                        >
                            {(['light', 'dark', 'system'] as const).map(
                                (mode) => (
                                    <ToggleGroupItem
                                        key={mode}
                                        value={mode}
                                        aria-label={mode}
                                        className="flex-1"
                                    >
                                        {mode === 'light'
                                            ? 'Light'
                                            : mode === 'dark'
                                              ? 'Dark'
                                              : 'System'}
                                    </ToggleGroupItem>
                                ),
                            )}
                        </ToggleGroup>
                    </div>
                </div>
            </ModalShell>
        </Dialog>
    );
}
