<?php

namespace App\Http\Controllers;

use App\Events\Entity\Article\ArticleUpdated;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\User;
use App\Models\Tenants\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HocuspocusWebhook extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            throw new AccessDeniedHttpException();
        }

        /** @var ParameterBag $json */
        $json = $request->json();

        $data = $json->all();

        $events = ['connect', 'create', 'change', 'disconnect'];

        if (! in_array($data['event'], $events, true)) {
            throw new BadRequestException();
        }

        return $this->{$data['event']}($data['payload']);
    }

    /**
     * @param  array<string, string|array<string, string>>  $payload
     */
    protected function connect(array $payload): JsonResponse
    {
        /** @var array<string, string> $params */
        $params = $payload['requestParameters'];

        /** @var string $name */
        $name = $payload['documentName'];

        [
            'tid' => $params['tid'],
            'aid' => $params['aid'],
        ] = $this->extract($name);

        $user = $this->authorize($params['tid']);

        if (! $this->editable($user, $params['aid'])) {
            throw new AccessDeniedHttpException();
        }

        return response()->json([
            'tid' => $params['tid'],
            'aid' => $params['aid'],
            'uid' => $user->getKey(),
        ]);
    }

    /**
     * Extract tenant id and article id from document name.
     *
     * @return array<string, string>
     */
    protected function extract(string $name): array
    {
        $chunks = explode('.', $name, 2);

        if (count($chunks) !== 2) {
            throw new AccessDeniedHttpException();
        }

        [$tid, $aid] = $chunks;

        if (empty($tid) || empty($aid)) {
            throw new AccessDeniedHttpException();
        }

        return compact('tid', 'aid');
    }

    protected function authorize(string $tid): User
    {
        if (auth()->guest()) {
            throw new AccessDeniedHttpException();
        }

        $userId = auth()->user()?->getAuthIdentifier();

        try {
            tenancy()->initialize($tid);
        } catch (TenantCouldNotBeIdentifiedById) {
            throw new AccessDeniedHttpException();
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (is_null($user)) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    protected function editable(User $user, string $aid): bool
    {
        $article = Article::with('authors')->find($aid);

        if (! ($article instanceof Article)) {
            return false;
        }

        if (! Gate::forUser(auth()->user())->check('write', $article)) {
            return false;
        }

        if ($article->authors->where('id', $user->id)->isNotEmpty()) {
            return true;
        }

        if ($article->desk->open_access || $user->isInDesk($article->desk)) {
            return true;
        }

        $parent = $article->desk->desk;

        if ($parent === null) {
            return false;
        }

        return $parent->open_access || $user->isInDesk($parent);
    }

    /**
     * @param  array<string, string|array<string, string>>  $payload
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    protected function create(array $payload): JsonResponse
    {
        /** @var string $name */
        $name = $payload['documentName'];

        [
            'tid' => $tid,
            'aid' => $aid,
        ] = $this->extract($name);

        try {
            tenancy()->initialize($tid);
        } catch (TenantCouldNotBeIdentifiedById) {
            throw new NotFoundHttpException();
        }

        /** @var Article|null $article */
        $article = Article::find($aid);

        if ($article === null) {
            throw new NotFoundHttpException();
        }

        $document = $article->document ?: [];

        $options = empty($document) ? JSON_FORCE_OBJECT : 0;

        return response()->json(data: $document, options: $options);
    }

    /**
     * @param array{
     *     documentName: string,
     *     document: array{
     *         default: array<string, mixed>,
     *         annotations: array<string, mixed>,
     *         title?: array<string, mixed>,
     *         blurb?: array<string, mixed>,
     *     },
     *     context: array{
     *         tid: string,
     *         aid: string,
     *         uid?: string,
     *     },
     *     html?: string,
     *     plaintext?: string,
     * } $payload
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    protected function change(array $payload): JsonResponse
    {
        /** @var array<string, string> $context */
        $context = $payload['context'];

        if (! isset($context['tid'], $context['aid'])) {
            return response()->json();
        }

        [
            'tid' => $tid,
            'aid' => $aid,
        ] = $context;

        try {
            tenancy()->initialize($tid);
        } catch (TenantCouldNotBeIdentifiedById) {
            throw new NotFoundHttpException();
        }

        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        $article = Article::find($aid);

        if (! ($article instanceof Article)) {
            return response()->json();
        }

        $origin = $article->only(['title', 'blurb', 'document']);

        $article->document = $document = $payload['document'];

        if (isset($document['title'])) {
            $article->title = app('prosemirror')->toHTML($document['title'], [
                'client_id' => $tenant->id,
                'article_id' => $article->id,
            ]) ?: '';

            if (! $article->published && $origin['title'] !== $article->title) {
                $article->pathnames = array_merge($article->pathnames ?: [], [
                    time() => sprintf('/posts/%s', $article->slug),
                ]);

                $article->slug = '';
            }
        }

        if (isset($document['blurb'])) {
            $article->blurb = app('prosemirror')->toHTML($document['blurb'], [
                'client_id' => $tenant->id,
                'article_id' => $article->id,
            ]);
        }

        if (isset($payload['html'])) {
            $article->html = $payload['html'];
        }

        if (isset($payload['plaintext'])) {
            $article->plaintext = $payload['plaintext'];
        }

        $article->save();

        if (isset($context['uid'])) {
            UserActivity::log(
                name: 'article.content.update',
                subject: $article,
                data: [
                    'old' => $origin,
                    'new' => $article->only(['title', 'blurb', 'document']),
                ],
                userId: (int) $context['uid'],
            );
        }

        ArticleUpdated::dispatch(
            $tenant->id,
            $article->id,
            ['title', 'blurb', 'document'],
        );

        return response()->json();
    }

    protected function disconnect(): JsonResponse
    {
        return response()->json();
    }

    protected function verifySignature(Request $request): bool
    {
        $signature = $request->headers->get(
            'X-Hocuspocus-Signature-256',
        );

        if (empty($signature)) {
            return false;
        }

        $parts = explode('=', $signature);

        if (count($parts) != 2) {
            return false;
        }

        /** @var string $key */
        $key = config('services.storipress.api_key');

        $digest = hash_hmac(
            'sha256',
            (string) $request->getContent(),
            $key,
        );

        return hash_equals($digest, $parts[1]);
    }
}
