import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/react';

export type LibraryGlassSelectOption = { value: string; label: string };

export default function LibraryGlassSelect({
    id,
    value,
    onChange,
    options,
    disabled = false,
    className = '',
}: {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    options: LibraryGlassSelectOption[];
    disabled?: boolean;
    className?: string;
}) {
    const selected =
        options.find((o) => o.value === value) ?? options[0] ?? {
            value: '',
            label: '',
        };

    return (
        <Listbox value={value} onChange={onChange} disabled={disabled}>
            <div className={`relative ${className}`}>
                <ListboxButton
                    id={id}
                    type="button"
                    className="library-field-select flex w-full items-center justify-between gap-2 text-left"
                >
                    <span className="min-w-0 flex-1 truncate">{selected.label}</span>
                    <svg
                        className="h-4 w-4 shrink-0 text-[rgba(245,240,232,0.45)]"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden
                    >
                        <path
                            fillRule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                            clipRule="evenodd"
                        />
                    </svg>
                </ListboxButton>

                <ListboxOptions
                    modal={false}
                    className="absolute left-0 right-0 top-full z-[200] mt-1 max-h-60 min-h-0 w-full overflow-y-auto rounded-lg border border-white/10 bg-[rgba(42,28,18,0.92)] py-1 shadow-[0_16px_48px_rgba(0,0,0,0.45)] outline-none backdrop-blur-xl"
                >
                    {options.map((opt) => (
                        <ListboxOption
                            key={opt.value === '' ? '__empty' : opt.value}
                            value={opt.value}
                            className="cursor-pointer px-3 py-2 text-sm text-[#f5f0e8] data-[focus]:bg-[rgba(201,168,76,0.14)] data-[selected]:bg-[rgba(201,168,76,0.08)] data-[selected]:font-medium data-[selected]:text-[#c9a84c]"
                        >
                            {opt.label}
                        </ListboxOption>
                    ))}
                </ListboxOptions>
            </div>
        </Listbox>
    );
}
