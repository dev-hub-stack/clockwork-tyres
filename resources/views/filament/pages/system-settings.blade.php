<x-filament-panels::page>
    <form wire:submit="save" class="fi-form flex flex-col gap-6">
        {{ $this->form }}

        <div class="fi-form-actions flex justify-end">
            <button type="submit" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-color-custom fi-ac-btn-action">
                Save
            </button>
        </div>
    </form>
</x-filament-panels::page>
