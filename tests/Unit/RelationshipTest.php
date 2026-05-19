<?php
namespace Tests\Unit;

use App\Models\Athlete;
use App\Models\Coach;
use App\Models\Sport;
use App\Models\Workout;
use App\Models\WorkoutStep;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanItem;
use App\Models\Event;
use App\Models\RaceEntry;
use App\Models\TrackingSession;
use App\Models\TelemetryPoint;
use App\Models\AthleteActivity;
use App\Models\Supporter;
use App\Models\NotificationSubscription;
use App\Models\GarminConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_athlete_coach_relationship()
    {
        $athlete = Athlete::factory()->create();
        $coach = Coach::factory()->create();
        $athlete->coaches()->attach($coach);
        $this->assertTrue($athlete->coaches->contains($coach));
        $this->assertTrue($coach->athletes->contains($athlete));
    }

    public function test_workout_relationships()
    {
        $athlete = Athlete::factory()->create();
        $sport = Sport::factory()->create();
        $workout = Workout::factory()->for($athlete)->for($sport)->create();
        $this->assertEquals($athlete->id, $workout->athlete->id);
        $this->assertEquals($sport->id, $workout->sport->id);
    }

    public function test_race_entry_and_tracking_session()
    {
        $athlete = Athlete::factory()->create();
        $event = Event::factory()->create();
        $raceEntry = RaceEntry::factory()->for($event)->for($athlete)->create();
        $session = TrackingSession::factory()->for($athlete)->for($raceEntry)->create();
        $this->assertEquals($raceEntry->id, $session->raceEntry->id);
        $this->assertEquals($athlete->id, $session->athlete->id);
    }

    public function test_telemetry_point_relationship()
    {
        $session = TrackingSession::factory()->create();
        $point = TelemetryPoint::factory()->for($session, 'trackingSession')->create();
        $this->assertEquals($session->id, $point->trackingSession->id);
    }

    public function test_athlete_activity_relationships()
    {
        $athlete = Athlete::factory()->create();
        $sport = Sport::factory()->create();
        $session = TrackingSession::factory()->for($athlete)->for($sport)->create();
        $activity = AthleteActivity::factory()
            ->for($athlete)
            ->for($sport)
            ->for($session, 'trackingSession')
            ->create();

        $this->assertEquals($athlete->id, $activity->athlete->id);
        $this->assertEquals($sport->id, $activity->sport->id);
        $this->assertEquals($session->id, $activity->trackingSession->id);
        $this->assertEquals($activity->id, $session->athleteActivity->id);
        $this->assertTrue($athlete->athleteActivities->contains($activity));
    }
}
