/**
 * Lightweight DOM Windowing / Virtualizer Utility
 * Calculates start and end indices and spacer padding for 60fps rendering of large collections.
 */
export class ListVirtualizer {
    constructor({
        totalItems = 0,
        itemHeight = 44,
        viewportHeight = 500,
        overscan = 5,
    } = {}) {
        this.totalItems = totalItems;
        this.itemHeight = itemHeight;
        this.viewportHeight = viewportHeight;
        this.overscan = overscan;
    }

    update({ totalItems, itemHeight, viewportHeight }) {
        if (totalItems !== undefined) this.totalItems = totalItems;
        if (itemHeight !== undefined) this.itemHeight = itemHeight;
        if (viewportHeight !== undefined) this.viewportHeight = viewportHeight;
    }

    calculateRange(scrollTop = 0) {
        const totalHeight = this.totalItems * this.itemHeight;
        
        if (this.totalItems === 0 || this.viewportHeight === 0) {
            return {
                startIndex: 0,
                endIndex: 0,
                topPadding: 0,
                bottomPadding: 0,
                totalHeight: 0,
                visibleCount: 0,
            };
        }

        const rawStart = Math.floor(scrollTop / this.itemHeight);
        const visibleItems = Math.ceil(this.viewportHeight / this.itemHeight);
        const rawEnd = rawStart + visibleItems;

        const startIndex = Math.max(0, rawStart - this.overscan);
        const endIndex = Math.min(this.totalItems, rawEnd + this.overscan);

        const topPadding = startIndex * this.itemHeight;
        const bottomPadding = Math.max(0, (this.totalItems - endIndex) * this.itemHeight);

        return {
            startIndex,
            endIndex,
            topPadding,
            bottomPadding,
            totalHeight,
            visibleCount: endIndex - startIndex,
        };
    }
}
