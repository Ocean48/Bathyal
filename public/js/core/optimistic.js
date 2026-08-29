/**
 * Optimistic UI Mutation Manager
 * Applies immediate state updates to the UI, performs API requests in background,
 * and rolls back state with toast feedback if the API request fails.
 */
import { toast } from '../components/Toast.js';
import { eventBus } from './eventBus.js';

class OptimisticManager {
    constructor() {
        this.pendingMutations = new Map();
        this.mutationCounter = 0;
    }

    async run({
        mutationType,
        optimisticApply,
        rollback,
        apiCall,
        successMessage = null,
        errorMessage = 'Action failed. Changes rolled back.',
    }) {
        const mutationId = ++this.mutationCounter;
        this.pendingMutations.set(mutationId, { mutationType, rollback });

        try {
            // 1. Immediately apply mutation
            if (typeof optimisticApply === 'function') {
                optimisticApply();
            }
            eventBus.emit('optimistic:applied', { mutationId, mutationType });

            // 2. Perform background API call
            const result = await apiCall();

            // 3. Confirm success
            this.pendingMutations.delete(mutationId);
            eventBus.emit('optimistic:confirmed', { mutationId, mutationType, result });

            if (successMessage) {
                toast.success(successMessage);
            }

            return result;
        } catch (err) {
            console.error(`Optimistic mutation [${mutationType}] failed:`, err);

            // 4. Rollback
            if (typeof rollback === 'function') {
                try {
                    rollback();
                } catch (rbErr) {
                    console.error('Error during optimistic rollback:', rbErr);
                }
            }

            this.pendingMutations.delete(mutationId);
            eventBus.emit('optimistic:rolled_back', { mutationId, mutationType, error: err });

            const msg = err && err.message ? `${errorMessage}: ${err.message}` : errorMessage;
            toast.error(msg);

            throw err;
        }
    }
}

export const optimistic = new OptimisticManager();
