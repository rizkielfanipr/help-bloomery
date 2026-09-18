<x-filament-panels::page>
    <div
        x-data="locationFloorPlan({
            initialTree: @js($this->getTree()),
            typeOptions: @js($typeOptions),
            editUrlTemplate: '{{ route('filament.helpdesk.resources.locations.edit', ['record' => '__ID__']) }}',
        })"
        wire:ignore
        class="space-y-4"
    >
        @if ($branchId === null)
            <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Pilih cabang untuk melihat denah lokasi.</p>
                <div class="flex flex-wrap justify-center gap-2">
                    @foreach ($accessibleBranches as $branch)
                        <a href="{{ route('filament.helpdesk.resources.locations.floor-plan', ['branch_id' => $branch->id]) }}"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            {{ $branch->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <div class="flex flex-col gap-4 lg:flex-row">
                <div class="flex-1 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ $locations->count() }} lokasi &middot; klik kotak untuk pilih, seret untuk memindah, tarik sudut untuk mengubah ukuran
                        </p>
                        <button
                            type="button"
                            x-show="dirty"
                            x-cloak
                            x-on:click="save()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                        >
                            Simpan Layout
                        </button>
                    </div>

                    <div
                        x-ref="canvas"
                        class="relative aspect-square w-full touch-none select-none overflow-hidden bg-[linear-gradient(to_right,theme(colors.gray.200)_1px,transparent_1px),linear-gradient(to_bottom,theme(colors.gray.200)_1px,transparent_1px)] bg-[size:10%_10%] dark:bg-gray-900 dark:bg-[linear-gradient(to_right,theme(colors.gray.800)_1px,transparent_1px),linear-gradient(to_bottom,theme(colors.gray.800)_1px,transparent_1px)]"
                        x-on:pointermove="onPointerMove($event)"
                        x-on:pointerup="stopInteraction()"
                        x-on:pointerleave="stopInteraction()"
                        x-on:click="selectedId = null; highlightSelected()"
                    ></div>
                </div>

                <div class="w-full shrink-0 space-y-4 lg:w-80">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="mb-1 text-sm font-semibold text-gray-800 dark:text-gray-100">Tambah Lokasi</h3>
                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            <span x-show="selectedNode" x-text="'Menambah di dalam: ' + (selectedNode ? selectedNode.name : '')"></span>
                            <span x-show="! selectedNode">Menambah di level utama (root)</span>
                        </p>
                        <form x-on:submit.prevent="createChild()" class="space-y-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Nama</label>
                                <input type="text" x-model="form.name" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Tipe</label>
                                <select x-model="form.type" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                    <option value="" disabled>— Pilih Tipe —</option>
                                    <template x-for="type in typeOptions" :key="type">
                                        <option :value="type" x-text="type"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Kode Segmen</label>
                                <input type="text" x-model="form.segment" required
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                            </div>
                            <p x-show="formError" x-cloak x-text="formError" class="text-xs text-danger-600 dark:text-danger-400"></p>
                            <button type="submit" class="w-full rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                                Tambah
                            </button>
                        </form>
                    </div>

                    <template x-if="selectedNode">
                        <div class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="selectedNode.name"></h3>
                                <button type="button" x-on:click="selectedId = null; highlightSelected()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">&times;</button>
                            </div>
                            <p class="font-mono text-xs text-gray-500 dark:text-gray-400" x-text="selectedNode.code"></p>
                            <a :href="editUrlTemplate.replace('__ID__', selectedNode.id)"
                                class="block rounded-lg border border-gray-200 px-3 py-1.5 text-center text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Edit</a>
                            <button type="button" x-on:click="removeNode(selectedNode.id)"
                                class="w-full rounded-lg border border-danger-200 px-3 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-50 dark:border-danger-900 dark:hover:bg-danger-950/30">
                                Hapus
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        @endif
    </div>

    <script>
        function locationFloorPlan({ initialTree, typeOptions, editUrlTemplate }) {
            return {
                tree: initialTree,
                elements: {},
                containers: {},
                selectedId: null,
                dirty: false,
                dragging: null,
                resizing: null,
                form: { name: '', type: '', segment: '' },
                formError: '',
                typeOptions,
                editUrlTemplate,

                get selectedNode() {
                    return this.selectedId ? this.findNode(this.selectedId, this.tree) : null;
                },

                findNode(id, nodes) {
                    for (const node of nodes) {
                        if (node.id === id) {
                            return node;
                        }
                        const found = this.findNode(id, node.children);
                        if (found) {
                            return found;
                        }
                    }
                    return null;
                },

                findParentArray(id, nodes) {
                    for (const node of nodes) {
                        if (node.id === id) {
                            return nodes;
                        }
                        const found = this.findParentArray(id, node.children);
                        if (found) {
                            return found;
                        }
                    }
                    return null;
                },

                init() {
                    this.containers.root = this.$refs.canvas;
                    this.tree.forEach((node) => this.renderNode(node, this.$refs.canvas));

                    window.addEventListener('beforeunload', (event) => {
                        if (this.dirty) {
                            event.preventDefault();
                            event.returnValue = '';
                        }
                    });
                },

                // Builds one location's box as a plain DOM node (not an Alpine
                // x-for template) and recurses into its children so nesting can
                // go arbitrarily deep — Alpine's x-for can't cleanly recurse
                // into itself, but a plain recursive function can.
                renderNode(node, parentContainer) {
                    const box = document.createElement('div');
                    box.className = 'absolute flex flex-col rounded-md border-2 cursor-move overflow-hidden';
                    box.style.left = node.pos_x + '%';
                    box.style.top = node.pos_y + '%';
                    box.style.width = node.width + '%';
                    box.style.height = node.height + '%';

                    const header = document.createElement('div');
                    header.className = 'px-1 pt-0.5 pointer-events-none';
                    const nameEl = document.createElement('p');
                    nameEl.className = 'truncate text-[11px] font-semibold text-indigo-900 dark:text-indigo-100';
                    nameEl.textContent = node.name;
                    const codeEl = document.createElement('p');
                    codeEl.className = 'truncate font-mono text-[9px] text-indigo-700 dark:text-indigo-300';
                    codeEl.textContent = node.code;
                    header.append(nameEl, codeEl);
                    box.appendChild(header);

                    const childContainer = document.createElement('div');
                    childContainer.className = 'relative m-1 flex-1 rounded border border-dashed border-indigo-300/70 dark:border-indigo-700/70';
                    box.appendChild(childContainer);

                    const handle = document.createElement('div');
                    handle.className = 'absolute bottom-0 right-0 h-3 w-3 cursor-nwse-resize rounded-tl bg-primary-600';
                    box.appendChild(handle);

                    box.addEventListener('pointerdown', (event) => {
                        if (event.target === handle) {
                            return;
                        }
                        this.startDrag(event, node, box, parentContainer);
                    });
                    box.addEventListener('click', (event) => {
                        event.stopPropagation();
                        this.selectedId = node.id;
                        this.highlightSelected();
                    });
                    handle.addEventListener('pointerdown', (event) => {
                        event.stopPropagation();
                        this.startResize(event, node, box, parentContainer);
                    });

                    parentContainer.appendChild(box);
                    this.elements[node.id] = box;
                    this.containers[node.id] = childContainer;
                    this.applySelectionStyle(node.id);

                    node.children.forEach((child) => this.renderNode(child, childContainer));
                },

                applySelectionStyle(id) {
                    const el = this.elements[id];
                    if (! el) {
                        return;
                    }
                    const isSelected = id === this.selectedId;
                    el.classList.toggle('border-primary-600', isSelected);
                    el.classList.toggle('bg-primary-100', isSelected);
                    el.classList.toggle('dark:bg-primary-900/40', isSelected);
                    el.classList.toggle('border-indigo-400', ! isSelected);
                    el.classList.toggle('bg-indigo-100/80', ! isSelected);
                    el.classList.toggle('dark:border-indigo-500', ! isSelected);
                    el.classList.toggle('dark:bg-indigo-900/30', ! isSelected);
                },

                highlightSelected() {
                    Object.keys(this.elements).forEach((id) => this.applySelectionStyle(Number(id)));
                },

                // Converts a pointer event's viewport position into a percentage
                // (0-100) of the given container, matching the pos_x/pos_y/width/
                // height unit stored on each location. Percentages are always
                // relative to a box's own direct parent container, which is what
                // makes nested boxes position correctly inside their parent box.
                canvasPoint(container, event) {
                    const rect = container.getBoundingClientRect();

                    return {
                        x: ((event.clientX - rect.left) / rect.width) * 100,
                        y: ((event.clientY - rect.top) / rect.height) * 100,
                    };
                },

                startDrag(event, node, element, container) {
                    event.stopPropagation();
                    const point = this.canvasPoint(container, event);
                    this.selectedId = node.id;
                    this.highlightSelected();
                    this.dragging = { node, element, container, offsetX: point.x - node.pos_x, offsetY: point.y - node.pos_y };
                },

                startResize(event, node, element, container) {
                    const point = this.canvasPoint(container, event);
                    this.selectedId = node.id;
                    this.highlightSelected();
                    this.resizing = { node, element, container, startX: point.x, startY: point.y, startWidth: node.width, startHeight: node.height };
                },

                onPointerMove(event) {
                    if (this.dragging) {
                        const { node, element, container, offsetX, offsetY } = this.dragging;
                        const point = this.canvasPoint(container, event);
                        node.pos_x = Math.max(0, Math.min(100 - node.width, point.x - offsetX));
                        node.pos_y = Math.max(0, Math.min(100 - node.height, point.y - offsetY));
                        element.style.left = node.pos_x + '%';
                        element.style.top = node.pos_y + '%';
                        this.dirty = true;
                    } else if (this.resizing) {
                        const { node, element, container, startX, startY, startWidth, startHeight } = this.resizing;
                        const point = this.canvasPoint(container, event);
                        node.width = Math.max(6, Math.min(100 - node.pos_x, startWidth + (point.x - startX)));
                        node.height = Math.max(6, Math.min(100 - node.pos_y, startHeight + (point.y - startY)));
                        element.style.width = node.width + '%';
                        element.style.height = node.height + '%';
                        this.dirty = true;
                    }
                },

                stopInteraction() {
                    this.dragging = null;
                    this.resizing = null;
                },

                async save() {
                    const flat = [];
                    const collect = (nodes) => {
                        nodes.forEach((node) => {
                            flat.push({ id: node.id, pos_x: node.pos_x, pos_y: node.pos_y, width: node.width, height: node.height });
                            collect(node.children);
                        });
                    };
                    collect(this.tree);

                    await this.$wire.saveLayout(flat);
                    this.dirty = false;
                },

                async createChild() {
                    this.formError = '';

                    try {
                        const newNode = await this.$wire.createChild({ ...this.form, parent_id: this.selectedId });
                        newNode.children = [];

                        if (newNode.parent_id) {
                            this.findNode(newNode.parent_id, this.tree).children.push(newNode);
                            this.renderNode(newNode, this.containers[newNode.parent_id]);
                        } else {
                            this.tree.push(newNode);
                            this.renderNode(newNode, this.$refs.canvas);
                        }

                        this.form = { name: '', type: '', segment: '' };
                    } catch (error) {
                        this.formError = 'Gagal menambah lokasi. Periksa kembali data yang diisi.';
                    }
                },

                async removeNode(id) {
                    if (! confirm('Hapus lokasi ini?')) {
                        return;
                    }

                    const deleted = await this.$wire.deleteNode(id);

                    if (deleted) {
                        const siblings = this.findParentArray(id, this.tree);
                        if (siblings) {
                            const index = siblings.findIndex((node) => node.id === id);
                            if (index !== -1) {
                                siblings.splice(index, 1);
                            }
                        }

                        this.elements[id]?.remove();
                        delete this.elements[id];
                        delete this.containers[id];
                        this.selectedId = null;
                    }
                },
            };
        }
    </script>
</x-filament-panels::page>
