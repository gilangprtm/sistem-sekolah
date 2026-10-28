const MAX_STUDENT_CARD_ADDRESS_LINE_LENGTH = 19;

export function wrapStudentCardAddress(address: string | null): string {
    const characters = Array.from(address?.trim().replace(/\s+/gu, ' ') || '-');
    const lines: string[] = [];

    while (characters.length > 0) {
        lines.push(
            characters
                .splice(0, MAX_STUDENT_CARD_ADDRESS_LINE_LENGTH)
                .join('')
                .trim(),
        );
    }

    return lines.join('\n');
}
