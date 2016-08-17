<?php

namespace eLife\Journal\Controller;

use eLife\ApiSdk\ApiClient\EventsClient;
use eLife\ApiSdk\Exception\BadResponse;
use eLife\ApiSdk\MediaType;
use eLife\ApiSdk\Result;
use eLife\Patterns\ViewModel\ContentHeaderNonArticle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class EventsController extends Controller
{
    public function listAction() : Response
    {
        $page = 1;
        $perPage = 6;

        $arguments = $this->defaultPageArguments();

        $arguments['contentHeader'] = ContentHeaderNonArticle::basic('eLife events');

        $arguments['upcomingEvents'] = $this->get('elife.api_sdk.events')
            ->listEvents(['Accept' => new MediaType(EventsClient::TYPE_EVENT_LIST, 1)], $page, $perPage, 'open', false)
            ->then(function (Result $result) use ($arguments) {
                if (empty($result['items'])) {
                    return null;
                }

                $items = array_map(function (array $item) {
                    $item['type'] = 'event';

                    return $item;
                }, $result['items']);

                return $this->get('elife.journal.view_model.factory.listing_teaser')
                    ->forItems($items, 'Upcoming events');
            });

        return new Response($this->get('templating')->render('::events.html.twig', $arguments));
    }

    public function eventAction(string $id) : Response
    {
        $arguments = $this->defaultPageArguments();

        $arguments['event'] = $this->get('elife.api_sdk.events')
            ->getEvent(['Accept' => new MediaType(EventsClient::TYPE_EVENT, 1)], $id)
            ->otherwise(function (Throwable $e) {
                if ($e instanceof BadResponse && 404 === $e->getResponse()->getStatusCode()) {
                    throw new NotFoundHttpException('Event not found', $e);
                }
            });

        $arguments['contentHeader'] = $arguments['event']
            ->then(function (Result $event) {
                return ContentHeaderNonArticle::basic($event['title']);
            });

        return new Response($this->get('templating')->render('::event.html.twig', $arguments));
    }
}