<?php

declare(strict_types=1);

use Livewire\Component;
use Salioudiabate\Notify\Builders\ConfirmBuilder;
use Salioudiabate\Notify\Builders\ToastBuilder;
use Salioudiabate\Notify\Concerns\InteractsWithNotifications;

function fakeNotifyComponent(): Component
{
    $component = new class extends Component
    {
        use InteractsWithNotifications;

        public array $dispatched = [];

        public function dispatch($event, ...$params)
        {
            $this->dispatched[] = ['event' => $event, 'params' => $params];

            return parent::dispatch($event, ...$params);
        }
    };
    $component->setId('fake-component-id');
    $component->bootInteractsWithNotifications();

    return $component;
}

it('notify() sends a success toast immediately when a message is given', function () {
    $component = fakeNotifyComponent();

    $component->notify('Créé.');

    expect($component->dispatched)->toHaveCount(1);
    $notification = $component->dispatched[0]['params']['notification'];
    expect($notification)->variant->toBe('success')->message->toBe('Créé.');
});

it('notify() with no arguments returns the builder instead of sending', function () {
    $component = fakeNotifyComponent();

    $builder = $component->notify();

    expect($builder)->toBeInstanceOf(ToastBuilder::class)
        ->and($component->dispatched)->toBeEmpty();
});

it('notifyBuilder() always returns the builder, unambiguously, unlike notify()', function () {
    $component = fakeNotifyComponent();

    $builder = $component->notifyBuilder();

    expect($builder)->toBeInstanceOf(ToastBuilder::class)
        ->and($component->dispatched)->toBeEmpty();

    $builder->asError()->message('Oups')->send();

    expect($component->dispatched)->toHaveCount(1);
});

it('confirm() sends the confirm dialog immediately, bound to this component', function () {
    $component = fakeNotifyComponent();

    $component->confirm('Supprimer ?', 'Action définitive.', 'delete', ['id' => 42]);

    expect($component->dispatched)->toHaveCount(1);
    $notification = $component->dispatched[0]['params']['notification'];
    expect($notification['type'])->toBe('confirm')
        ->and($notification['actions'][1]['target'])->toBe([
            'type' => 'livewire',
            'component' => 'fake-component-id',
            'method' => 'delete',
            'params' => ['id' => 42],
        ]);
});

it('confirmBuilder() returns the builder so ->danger()/->confirmColor()/... can be chained before send()', function () {
    $component = fakeNotifyComponent();

    $builder = $component->confirmBuilder('Supprimer ?', 'Action définitive.');

    expect($builder)->toBeInstanceOf(ConfirmBuilder::class)
        ->and($component->dispatched)->toBeEmpty();

    $builder->danger()->confirmColor('#dc2626')->onConfirm('delete')->send();

    expect($component->dispatched)->toHaveCount(1);
    $notification = $component->dispatched[0]['params']['notification'];
    expect($notification['variant'])->toBe('error')
        ->and($notification['actions'][1]['color'])->toBe(['bg' => '#dc2626']);
});
