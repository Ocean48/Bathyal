/**
 * Pluggable Component & Widget Registry
 */
export class ComponentRegistry {
    constructor() {
        this.components = new Map();
        this.mountedInstances = new Map();
    }

    register(name, componentClass) {
        this.components.set(name, componentClass);
    }

    mount(name, container, props = {}) {
        if (!this.components.has(name)) {
            console.warn(`Component [${name}] is not registered.`);
            return null;
        }

        this.unmount(container);

        const ComponentClass = this.components.get(name);
        const instance = new ComponentClass(container, props);
        if (typeof instance.mount === 'function') {
            instance.mount();
        }

        this.mountedInstances.set(container, instance);
        return instance;
    }

    unmount(container) {
        if (this.mountedInstances.has(container)) {
            const instance = this.mountedInstances.get(container);
            if (typeof instance.unmount === 'function') {
                instance.unmount();
            }
            this.mountedInstances.delete(container);
        }
    }
}

export const componentRegistry = new ComponentRegistry();
