// Font registry for Inertia/Vite (no next/font)
export const fontRegistry = {
    geist: { label: 'Geist', className: 'font-geist' },
    inter: { label: 'Inter', className: 'font-inter' },
    notoSans: { label: 'Noto Sans', className: 'font-noto-sans' },
    nunitoSans: { label: 'Nunito Sans', className: 'font-nunito-sans' },
    figtree: { label: 'Figtree', className: 'font-figtree' },
    roboto: { label: 'Roboto', className: 'font-roboto' },
    raleway: { label: 'Raleway', className: 'font-raleway' },
    dmSans: { label: 'DM Sans', className: 'font-dm-sans' },
    publicSans: { label: 'Public Sans', className: 'font-public-sans' },
    outfit: { label: 'Outfit', className: 'font-outfit' },
    geistMono: { label: 'Geist Mono', className: 'font-geist-mono' },
    jetBrainsMono: {
        label: 'JetBrains Mono',
        className: 'font-jetbrains-mono',
    },
    notoSerif: { label: 'Noto Serif', className: 'font-noto-serif' },
    robotoSlab: { label: 'Roboto Slab', className: 'font-roboto-slab' },
    merriweather: { label: 'Merriweather', className: 'font-merriweather' },
    lora: { label: 'Lora', className: 'font-lora' },
    playfairDisplay: {
        label: 'Playfair Display',
        className: 'font-playfair-display',
    },
} as const;

export type FontKey = keyof typeof fontRegistry;

export const fontKeys = Object.keys(fontRegistry) as FontKey[];

export const fontVars = ''; // Not used in Vite

export const fontOptions = fontKeys.map((key) => ({
    key,
    label: fontRegistry[key].label,
}));
