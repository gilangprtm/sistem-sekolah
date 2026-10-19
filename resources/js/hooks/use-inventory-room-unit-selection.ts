import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export type InventoryRoomUnit = {
    id: number;
    display_code: string;
    item_name: string;
    item_brand: string | null;
    condition: string;
    room: { id: number; name: string; code: string } | null;
};

export type InventoryRoomUnitPaginator = {
    data: InventoryRoomUnit[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type UseInventoryRoomUnitSelectionOptions = {
    lookupUnits: InventoryRoomUnitPaginator;
    lookupSearch?: string;
    lookupUrl: string;
    initialSelectedUnits?: number[];
    initialSelectedUnitDetails?: Record<number, InventoryRoomUnit>;
};

export function useInventoryRoomUnitSelection({
    lookupUnits,
    lookupSearch = '',
    lookupUrl,
    initialSelectedUnits = [],
    initialSelectedUnitDetails = {},
}: UseInventoryRoomUnitSelectionOptions) {
    const [selectedUnits, setSelectedUnits] =
        useState<number[]>(initialSelectedUnits);
    const [selectedUnitDetails, setSelectedUnitDetails] = useState<
        Record<number, InventoryRoomUnit>
    >(initialSelectedUnitDetails);
    const [lookupSelectedUnits, setLookupSelectedUnits] = useState<number[]>(
        [],
    );
    const [lookupOpen, setLookupOpen] = useState(false);
    const [currentLookupSearch, setCurrentLookupSearch] =
        useState(lookupSearch);
    const [selectedPage, setSelectedPage] = useState(1);
    const [selectedPerPage, setSelectedPerPage] = useState(10);

    const selectedUnitRows = useMemo(
        () =>
            selectedUnits.map((id) => selectedUnitDetails[id]).filter(Boolean),
        [selectedUnitDetails, selectedUnits],
    );
    const selectedLastPage = Math.max(
        1,
        Math.ceil(selectedUnitRows.length / selectedPerPage),
    );
    const paginatedSelectedUnitRows = selectedUnitRows.slice(
        (selectedPage - 1) * selectedPerPage,
        selectedPage * selectedPerPage,
    );
    const selectedPaginator = {
        current_page: selectedPage,
        last_page: selectedLastPage,
        per_page: selectedPerPage,
        total: selectedUnitRows.length,
    };

    const openLookup = () => {
        setLookupSelectedUnits(selectedUnits);
        setLookupOpen(true);
    };

    const loadLookupPage = (page: number, perPage = lookupUnits.per_page) => {
        router.get(
            lookupUrl,
            {
                lookup_search: currentLookupSearch || undefined,
                lookup_page: page,
                lookup_per_page: perPage,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirmLookup = () => {
        setSelectedUnits(lookupSelectedUnits);
        setSelectedPage(1);
        setLookupOpen(false);
    };

    const setLookupSelection = (
        unitIds: number[],
        units: InventoryRoomUnit[] = [],
    ) => {
        setLookupSelectedUnits(unitIds);

        if (units.length > 0) {
            setSelectedUnitDetails((current) => ({
                ...current,
                ...Object.fromEntries(units.map((unit) => [unit.id, unit])),
            }));
        }
    };

    const toggleLookupUnit = (unitId: number, checked: boolean) => {
        if (checked) {
            const unit = lookupUnits.data.find(
                (candidate) => candidate.id === unitId,
            );

            if (unit) {
                setSelectedUnitDetails((current) => ({
                    ...current,
                    [unitId]: unit,
                }));
            }
        }

        setLookupSelection(
            checked
                ? lookupSelectedUnits.includes(unitId)
                    ? lookupSelectedUnits
                    : [...lookupSelectedUnits, unitId]
                : lookupSelectedUnits.filter((id) => id !== unitId),
        );
    };

    const removeSelectedUnit = (unitId: number) => {
        setSelectedUnits((current) => current.filter((id) => id !== unitId));
        setSelectedPage(1);
        setSelectedUnitDetails((current) => {
            const next = { ...current };
            delete next[unitId];

            return next;
        });
    };

    return {
        currentLookupSearch,
        lookupOpen,
        lookupSelectedUnits,
        paginatedSelectedUnitRows,
        selectedPaginator,
        selectedPerPage,
        selectedLastPage,
        selectedPage,
        selectedUnitRows,
        selectedUnits,
        setCurrentLookupSearch,
        setLookupOpen,
        setSelectedPage,
        setSelectedPerPage,
        setLookupSelection,
        loadLookupPage,
        openLookup,
        confirmLookup,
        removeSelectedUnit,
        toggleLookupUnit,
    };
}
