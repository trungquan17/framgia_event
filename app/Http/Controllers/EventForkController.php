<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EventPlan;
use App\Models\EventFork;
use App\Models\EventForkDetail;
use App\Models\ForkPlanService;
use App\Events\ForkClickElementEvent;
use App\Events\ForkLosesElementEvent;
use App\Events\ForkChangedDataEvent;
use App\Events\ForkLiveChatEvent;
use DB;

class EventForkController extends Controller
{
    public function forkEventPlan($userId, $eventPlanId)
    {
        $eventPlan = EventPlan::getEventPlanById($eventPlanId)
            ->with('eventPlanDetails', 'eventForks')->first();

        if ($eventPlan->user_id == $userId) {
            return redirect()->route('home');
        }

        if (count($eventPlan->eventForks)) {
            foreach ($eventPlan->eventForks as $eventFork) {
                if ($eventFork->user_id == $userId ||
                    $eventFork->eventPlan->user_id == $userId ) {
                    return view('front.event_forks.show', compact('eventFork'));
                }
            }
        }
        $eventFork = DB::transaction(function () use ($eventPlan, $userId)
        {
            $newEventFork = EventFork::create([
                'slug' => $eventPlan->slug,
                'amount' => $eventPlan->amount,
                'event_plan_id' => $eventPlan->id,
                'user_id' => $userId,
            ]);
            foreach ($eventPlan->eventPlanDetails as $detail) {
                $eventForkDetail = EventForkDetail::create([
                    'name' => $detail->name,
                    'start_date' => $detail->start_date,
                    'due_date' => $detail->due_date,
                    'amount' => $detail->amount,
                    'status' => config('asset.active.yes'),
                    'event_fork_id' => $newEventFork->id,
                ]);

                $forkPlanServices = ForkPlanService::findByEventPlanDetail($detail->id)->get();
                foreach ($forkPlanServices as $forkService) {
                    ForkPlanService::create([
                        'service_id' => $forkService->service_id,
                        'event_fork_detail_id' => $eventForkDetail->id,
                    ]);
                }
            }

            return $newEventFork;
        });

        return view('front.event_forks.show', compact('eventFork'));
    }

    public function clickElement(Request $request)
    {
        if (!$request->ajax()) {
            return view('errors.403');
        }
        $elementId = $request->elementId;
        event(new ForkClickElementEvent($elementId));
    }

    public function losesElement(Request $request)
    {
        if (!$request->ajax()) {
            return view('errors.403');
        }
        $elementId = $request->elementId;
        event(new ForkLosesElementEvent($elementId));
    }

    public function changedData(Request $request)
    {
        if (!$request->ajax()) {
            return view('errors.403');
        }
        $elementId = $request->elementId;
        $elementValue = $request->elementValue;
        event(new ForkChangedDataEvent($elementId, $elementValue));
    }

    public function liveChat(Request $request)
    {
        if (!$request->ajax()) {
            return view('errors.403');
        }
        $user = $request->user;
        $content = $request->content;
        $chanelId = $request->chanelId;

        event(new ForkLiveChatEvent($user, $content, $chanelId));
    }
}
