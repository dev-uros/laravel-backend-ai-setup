<?php

namespace App\Support\Scramble;

use App\Http\Responses\ApiResponse;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Teaches Scramble what {@see ApiResponse} produces.
 *
 * Without this, every documented endpoint shows an empty `object` response,
 * because Scramble reads the controller's return statement and cannot see
 * through the static call into the envelope builder.
 *
 * Adding a method to ApiResponse means adding it to the match below, otherwise
 * endpoints using it silently lose their response schema.
 */
class ApiResponseTypeInfer implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === ApiResponse::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        $status = match ($event->name) {
            'success' => $event->getArg('status', 2, new LiteralIntegerType(200)),
            'created' => new LiteralIntegerType(201),
            default => null,
        };

        if ($status === null) {
            return null;
        }

        $data = $event->getArg('data', 1, new NullType);

        $items = [
            new ArrayItemType_('message', new StringType),
            new ArrayItemType_('data', $data),
        ];

        /**
         * ApiResponse renders a resource through its own response, so a
         * paginated collection arrives with `links` and `meta` beside `data`
         * rather than nested under it. Scramble only builds those two keys
         * while transforming a *response*, and here the collection is a value
         * inside the envelope, so they have to be declared.
         */
        if ($this->isPaginated($data)) {
            $items[] = new ArrayItemType_('links', $this->linksType());
            $items[] = new ArrayItemType_('meta', $this->metaType());
        }

        return new Generic(JsonResponse::class, [
            new KeyedArrayType($items),
            $status,
            new ArrayType,
        ]);
    }

    /**
     * Whether the data is a resource collection wrapping a paginator.
     */
    private function isPaginated(Type $data): bool
    {
        if (! $data instanceof Generic || ! $data->isInstanceOf(ResourceCollection::class)) {
            return false;
        }

        $collected = $data->templateTypes[/* TResource */ 0] ?? null;

        return $collected instanceof ObjectType
            && ($collected->isInstanceOf(Paginator::class) || $collected->isInstanceOf(AbstractPaginator::class));
    }

    /**
     * `PaginatedResourceResponse::paginationLinks()`.
     */
    private function linksType(): KeyedArrayType
    {
        $optionalUrl = new Union([new StringType, new NullType]);

        return new KeyedArrayType([
            new ArrayItemType_('first', new StringType),
            new ArrayItemType_('last', new StringType),
            new ArrayItemType_('prev', $optionalUrl),
            new ArrayItemType_('next', $optionalUrl),
        ]);
    }

    /**
     * `LengthAwarePaginator::toArray()` minus the keys `meta()` excludes, in
     * the order the paginator writes them.
     */
    private function metaType(): KeyedArrayType
    {
        $nullableInt = new Union([new IntegerType, new NullType]);

        return new KeyedArrayType([
            new ArrayItemType_('current_page', new IntegerType),
            new ArrayItemType_('from', $nullableInt),
            new ArrayItemType_('last_page', new IntegerType),
            new ArrayItemType_('links', new ArrayType(value: new KeyedArrayType([
                new ArrayItemType_('url', new Union([new StringType, new NullType])),
                new ArrayItemType_('label', new StringType),
                /** The separator entry ("...") carries no page. */
                new ArrayItemType_('page', $nullableInt, isOptional: true),
                new ArrayItemType_('active', new BooleanType),
            ]))),
            new ArrayItemType_('path', new StringType),
            new ArrayItemType_('per_page', new IntegerType),
            new ArrayItemType_('to', $nullableInt),
            new ArrayItemType_('total', new IntegerType),
        ]);
    }
}
