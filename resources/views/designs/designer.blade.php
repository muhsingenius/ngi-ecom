@php
    $fontFamilies = collect($config['fonts'])->map(fn ($f) => strtolower(str_replace(' ', '-', $f)).':400,700')->implode('|');
@endphp

<x-layouts.app :title="'Design your '.strtolower($product->type->label())">
    @push('head')
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $fontFamilies }}&display=swap">
        @vite('resources/js/designer.js')
    @endpush

    <script type="application/json" id="designer-config">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>

    <div x-data="designer" class="pb-28 lg:pb-0">
        <div class="container-page py-6 lg:py-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <a href="{{ $config['urls']['back'] }}" class="link-arrow text-sm">← {{ $product->name }} · {{ $product->quality }}</a>
                    <h1 class="type-title mt-2 !text-[1.6rem] md:!text-[2rem]">Design your {{ strtolower($product->type->label()) }}</h1>
                </div>

                <div class="hidden items-center gap-3 lg:flex">
                    <label for="design-name" class="sr-only">Design name</label>
                    <input id="design-name" x-model="designName" maxlength="120" placeholder="Name this design (optional)" class="field w-64">
                    <button type="button" class="btn btn-brand btn-lg" x-on:click="save()" x-bind:disabled="saving || busy">
                        <span x-text="saving ? 'Saving…' : 'Save and continue'"></span>
                    </button>
                </div>
            </div>

            <p x-show="restored" x-cloak class="mt-4 flex flex-wrap items-center gap-3 rounded-control bg-accent-tint px-4 py-3 text-sm text-ink">
                We restored your last session on this device.
                <button type="button" class="font-semibold underline" x-on:click="startOver()">Start over</button>
            </p>

            <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
                {{-- Stage --}}
                <div>
                    @if (count($config['areas']) > 1)
                        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Print area">
                            @foreach ($config['areas'] as $a)
                                <button type="button" role="tab" x-on:click="switchArea({{ $a['id'] }})"
                                        x-bind:aria-selected="areaId === {{ $a['id'] }}"
                                        class="btn" x-bind:class="areaId === {{ $a['id'] }} ? 'btn-brand' : 'btn-outline'">{{ $a['name'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="relative mx-auto mt-4 w-full max-w-[34rem] overflow-hidden rounded-card bg-mist">
                        <img x-bind:src="mockup" alt="Product mockup" draggable="false" class="block w-full select-none">

                        @foreach ($config['areas'] as $a)
                            <div data-box x-show="areaId === {{ $a['id'] }}" class="absolute outline outline-1 outline-dashed outline-brand/50"
                                 style="left: {{ $a['box']['x'] }}%; top: {{ $a['box']['y'] }}%; width: {{ $a['box']['w'] }}%; aspect-ratio: {{ $a['widthCm'] }} / {{ $a['heightCm'] }};">
                                <canvas id="canvas-{{ $a['id'] }}"></canvas>
                            </div>
                        @endforeach
                    </div>

                    <p class="mx-auto mt-3 max-w-[34rem] text-center text-xs text-muted" x-text="`${area.name}: ${area.widthCm} × ${area.heightCm} cm · ${area.methodLabel}`"></p>

                    @if (count($config['colours']) > 1)
                        <div class="mx-auto mt-5 max-w-[34rem]">
                            <p class="text-sm font-semibold text-ink">Product colour: <span class="font-normal text-copy" x-text="colours.find(c => c.id === colourId)?.name"></span></p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                @foreach ($config['colours'] as $c)
                                    <button type="button" x-on:click="setColour({{ $c['id'] }})" title="{{ $c['name'] }}" aria-label="{{ $c['name'] }}"
                                            class="size-9 rounded-full border border-line ring-offset-2"
                                            x-bind:class="colourId === {{ $c['id'] }} ? 'ring-2 ring-brand' : ''" style="background: {{ $c['hex'] }}"></button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tools --}}
                <div class="space-y-5">
                    <input type="file" x-ref="file" class="hidden" accept="image/png,image/jpeg,image/svg+xml,.svg" x-on:change="onFile($event)">

                    @if (! $config['textOnly'])
                        <div class="panel p-4">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2">
                                <button type="button" class="btn btn-brand" x-on:click="pickImage()" x-bind:disabled="busy">
                                    <x-icon name="upload" class="size-4" /> <span x-text="busy ? 'Uploading…' : 'Add image'"></span>
                                </button>
                                <button type="button" class="btn btn-outline" x-on:click="addText()"><x-icon name="type" class="size-4" /> Add text</button>
                                <template x-if="area.allowsName"><button type="button" class="btn btn-outline" x-on:click="addRole('name')">Add name</button></template>
                                <template x-if="area.allowsNumber"><button type="button" class="btn btn-outline" x-on:click="addRole('number')">Add number</button></template>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button type="button" class="btn btn-outline !min-h-10 flex-1" x-on:click="undo()" x-bind:disabled="! canUndo">Undo</button>
                                <button type="button" class="btn btn-outline !min-h-10 flex-1" x-on:click="redo()" x-bind:disabled="! canRedo">Redo</button>
                            </div>
                            <p class="mt-3 text-xs text-muted">PNG, JPG or SVG, up to <span x-text="cfg.maxUploadMb"></span> MB.</p>
                        </div>

                        {{-- Selected object --}}
                        <div class="panel space-y-5 p-4" x-show="selection" x-cloak>
                            <template x-if="selection && (selection.kind === 'text' || selection.kind === 'role')">
                                <div class="space-y-4">
                                    <p class="text-sm text-copy" x-show="selection.role">
                                        <span class="font-semibold text-ink" x-text="selection.role === 'name' ? 'Name placeholder' : 'Number placeholder'"></span>:
                                        style it here; each person's real text comes from the order list.
                                    </p>
                                    <div x-show="! selection.role">
                                        <label for="sel-text" class="block text-sm font-semibold text-ink">Text</label>
                                        <textarea id="sel-text" rows="2" class="field mt-1.5 py-2" x-bind:value="selection.text" x-on:input="setText($event.target.value)"></textarea>
                                    </div>
                                    <div>
                                        <label for="sel-font" class="block text-sm font-semibold text-ink">Font</label>
                                        <select id="sel-font" class="field mt-1.5" x-on:change="setFont($event.target.value)">
                                            <template x-for="f in cfg.fonts" :key="f">
                                                <option :value="f" :selected="f === selection.font" x-text="f" :style="`font-family: '${f}'`"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div x-show="palette.length > 1">
                                        <p class="text-sm font-semibold text-ink">Colour</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <template x-for="c in palette" :key="c.hex">
                                                <button type="button" :title="c.name" :aria-label="c.name" x-on:click="setFill(c.hex)"
                                                        class="size-8 rounded-full border border-line ring-offset-2"
                                                        :class="String(selection.fill).toLowerCase() === c.hex.toLowerCase() ? 'ring-2 ring-brand' : ''"
                                                        :style="`background:${c.hex}`"></button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div>
                                <label for="sel-size" class="flex justify-between text-sm font-semibold text-ink">Size <span class="font-normal text-muted" x-text="selection ? selection.widthPct + '% of the area' : ''"></span></label>
                                <input id="sel-size" type="range" min="5" max="150" class="mt-2 w-full accent-brand" x-bind:value="selection?.widthPct" x-on:input="setWidth($event.target.value)">
                            </div>
                            <div>
                                <label for="sel-rotate" class="flex justify-between text-sm font-semibold text-ink">Rotate <span class="font-normal text-muted" x-text="selection ? selection.angle + '°' : ''"></span></label>
                                <input id="sel-rotate" type="range" min="0" max="359" class="mt-2 w-full accent-brand" x-bind:value="selection?.angle" x-on:input="setAngle($event.target.value)">
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline !min-h-10 !px-3" x-on:click="centre()">Centre</button>
                                <button type="button" class="btn btn-outline !min-h-10 !px-3" x-on:click="forward()">Bring forward</button>
                                <button type="button" class="btn btn-outline !min-h-10 !px-3" x-on:click="backward()">Send back</button>
                                <button type="button" class="btn btn-outline !min-h-10 !px-3 !text-red-600" x-on:click="remove()">Delete</button>
                            </div>
                        </div>
                    @else
                        {{-- Bracelet: text and style, no canvas tools --}}
                        <div class="panel space-y-5 p-4">
                            <div>
                                <label for="bracelet-text" class="block text-sm font-semibold text-ink">Engraved text</label>
                                <input id="bracelet-text" class="field mt-1.5" x-model="bracelet.text" x-on:input="applyBracelet()"
                                       x-bind:maxlength="area.maxChars ?? 40" placeholder="Your text">
                                <p class="mt-1 text-xs text-muted" x-show="area.maxChars"><span x-text="bracelet.text.length"></span> / <span x-text="area.maxChars"></span> characters</p>
                            </div>
                            <div>
                                <label for="bracelet-font" class="block text-sm font-semibold text-ink">Style</label>
                                <select id="bracelet-font" class="field mt-1.5" x-model="bracelet.font" x-on:change="applyBracelet()">
                                    <template x-for="f in cfg.fonts" :key="f"><option :value="f" x-text="f"></option></template>
                                </select>
                            </div>
                        </div>
                    @endif

                    {{-- Embroidery colours --}}
                    <p class="text-sm text-copy" x-show="area.method === 'embroidery' && area.maxColours" x-cloak>
                        Thread colours: <span class="font-semibold text-ink" x-text="`${colourCounts[areaId] ?? 0} of ${area.maxColours}`"></span>
                    </p>

                    <template x-if="warnings.length">
                        <ul class="space-y-2" role="alert">
                            <template x-for="(w, i) in warnings" :key="i">
                                <li class="rounded-control px-3 py-2 text-sm" :class="w.level === 'error' ? 'bg-red-50 text-red-700' : 'bg-accent-tint text-ink'" x-text="w.text"></li>
                            </template>
                        </ul>
                    </template>

                    <p x-show="message" x-cloak x-text="message" class="rounded-control bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
                </div>
            </div>
        </div>

        {{-- Mobile action bar --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white p-3 shadow-float lg:hidden">
            <button type="button" class="btn btn-brand btn-lg btn-block" x-on:click="save()" x-bind:disabled="saving || busy">
                <span x-text="saving ? 'Saving…' : 'Save and continue'"></span>
            </button>
        </div>
    </div>
</x-layouts.app>
