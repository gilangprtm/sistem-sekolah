import { ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Option = { value: string; label: string };

type Props = {
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    className?: string;
    onSearchChange?: (value: string) => void;
    id?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

export default function SearchableCombobox({
    value,
    options,
    onChange,
    placeholder = 'Pilih opsi',
    searchPlaceholder = 'Cari...',
    emptyMessage = 'Tidak ditemukan.',
    className = 'w-full',
    onSearchChange,
    id,
    disabled = false,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: Props) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    aria-expanded={open}
                    className={`${className} justify-between font-normal`}
                >
                    <span className="truncate">
                        {selected?.label ?? placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[var(--radix-popover-trigger-width)] min-w-0 p-0"
            >
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder}
                        onValueChange={onSearchChange}
                    />
                    <CommandList>
                        <CommandEmpty>{emptyMessage}</CommandEmpty>
                        {options.map((option) => (
                            <CommandItem
                                key={option.value || 'empty'}
                                value={option.label}
                                data-checked={value === option.value}
                                onSelect={() => {
                                    onChange(option.value);
                                    setOpen(false);
                                }}
                            >
                                <span>{option.label}</span>
                            </CommandItem>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
