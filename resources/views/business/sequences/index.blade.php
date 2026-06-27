@extends('layouts.business')

@section('title', 'WhatsApp Sequence Planner')

@php
  $presets = [
    'no_response' => [
      'audience' => 'Cold or unresponsive leads',
      'objective' => 'Follow up after no reply and gently close the loop',
      'steps' => [
        ['title' => 'Check-in', 'delay_days' => 2, 'message' => 'Hi {{name}}, just checking in on my last message. If you still need help, I am here.'],
        ['title' => 'Value follow-up', 'delay_days' => 5, 'message' => 'Hi {{name}}, sharing one more helpful note in case you are still considering it. Happy to answer any questions.'],
        ['title' => 'Close the loop', 'delay_days' => 7, 'message' => 'Hi {{name}}, I do not want to crowd your inbox, so I will pause here. If you want to continue later, just reply anytime.'],
      ],
    ],
    'quick_reply' => [
      'audience' => 'Hot leads who replied fast',
      'objective' => 'Move the conversation to a demo, quote, or next step',
      'steps' => [
        ['title' => 'Thanks reply', 'delay_days' => 0, 'message' => 'Hi {{name}}, thanks for replying so quickly. I will help you with the next step now.'],
        ['title' => 'Details follow-up', 'delay_days' => 1, 'message' => 'Hi {{name}}, sending a quick follow-up with the details we discussed.'],
        ['title' => 'Confirmation', 'delay_days' => 3, 'message' => 'Hi {{name}}, just checking if you want me to move ahead with the next step.'],
      ],
    ],
    'custom' => [
      'audience' => 'Any audience',
      'objective' => 'Build your own sequence structure',
      'steps' => [
        ['title' => 'Step 1', 'delay_days' => 1, 'message' => ''],
        ['title' => 'Step 2', 'delay_days' => 3, 'message' => ''],
        ['title' => 'Step 3', 'delay_days' => 5, 'message' => ''],
      ],
    ],
  ];
@endphp

@section('content')
  <div class="card shadow-sm border-0 mt-3 mb-4" style="background: linear-gradient(135deg, #0f766e 0%, #14532d 100%); color: #fff;">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="text-uppercase small opacity-75 mb-2">Sequence Studio</div>
          <h4 class="mb-2"><i class="bi bi-diagram-3"></i> WhatsApp Sequence Planner</h4>
          <div class="opacity-75">Build the structure first, then queue it to one or many contacts. Bulk sending stays separate.</div>
        </div>
        <a href="{{ route('business.messages.create') }}" class="btn btn-light text-success fw-semibold">
          <i class="bi bi-send"></i> Open Send Messages
        </a>
      </div>
    </div>
  </div>

  <div class="row mt-4 g-4">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Sequence Blueprint</strong>
          <span class="badge bg-light text-dark border">Planner only</span>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('business.sequences.store') }}" class="row g-3">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Plan Name</label>
              <input type="text" class="form-control" name="plan_name" value="{{ old('plan_name', 'WhatsApp nurture sequence') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Audience</label>
              <input type="text" class="form-control" name="audience" value="{{ old('audience', 'Warm leads') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Goal</label>
              <input type="text" class="form-control" name="objective" value="{{ old('objective', 'Move the contact toward a reply') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Structure Type</label>
              <select class="form-select" name="sequence_type" id="sequencePreset">
                <option value="no_response" @selected(old('sequence_type', 'no_response') === 'no_response')>No Response</option>
                <option value="quick_reply" @selected(old('sequence_type') === 'quick_reply')>Quick Reply</option>
                <option value="custom" @selected(old('sequence_type') === 'custom')>Custom</option>
              </select>
            </div>

            @for ($step = 1; $step <= 3; $step++)
              <div class="col-12">
                <div class="border rounded-4 p-3 bg-light">
                  <div class="row g-3">
                    <div class="col-md-3">
                      <label class="form-label">Step Label</label>
                      <input type="text" class="form-control" name="step_title[{{ $step }}]" value="{{ old("step_title.$step", $step === 1 ? 'Quick Reply' : ($step === 2 ? 'Delayed Reply' : 'Follow-Up')) }}">
                    </div>
                    <div class="col-md-2">
                      <label class="form-label">Delay Days</label>
                      <input type="number" min="0" max="30" class="form-control" name="step_delay[{{ $step }}]" value="{{ old("step_delay.$step", $step === 1 ? 0 : ($step === 2 ? 0 : 2)) }}">
                    </div>
                    <div class="col-md-7">
                      <label class="form-label">Message</label>
                      <textarea class="form-control" rows="3" name="step_message[{{ $step }}]">{{ old("step_message.$step", $step === 1 ? 'Hi {{name}}, thanks for the quick reply. I am sending the next step now.' : ($step === 2 ? 'Hi {{name}}, thanks for getting back to me. Here is the next step based on the delay.' : 'Hi {{name}}, sharing one more helpful follow-up before I pause here.')) }}</textarea>
                    </div>
                  </div>
                </div>
              </div>
            @endfor

            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" rows="3" name="notes">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
              <button class="btn btn-success" type="submit" name="action" value="save">Save Plan</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mt-4 border-0">
        <div class="card-header bg-white">
          <strong>Saved Plans</strong>
        </div>
        <div class="table-responsive">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Plan</th>
                <th>Audience</th>
                <th>Objective</th>
                <th>Steps</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($plans ?? [] as $plan)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{ $plan->plan_name }}</td>
                  <td>{{ $plan->audience ?: '-' }}</td>
                  <td>{{ $plan->objective ?: '-' }}</td>
                  <td>{{ $plan->step_count }}</td>
                  <td><span class="badge bg-secondary">{{ $plan->status }}</span></td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-4">No sequence plans saved yet</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="card shadow-sm mt-4 border-0">
        <div class="card-header bg-white">
          <strong>Recent Follow-Ups</strong>
        </div>
        <div class="table-responsive">
          <table class="table table-striped mb-0 align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Contact</th>
                <th>Sequence</th>
                <th>Step</th>
                <th>Scheduled</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($followUps ?? [] as $followUp)
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{ $followUp->full_name }}<div class="text-muted small">{{ $followUp->phone_number }}</div></td>
                  <td>{{ $followUp->sequence_name }}</td>
                  <td>{{ $followUp->step_no }}</td>
                  <td>{{ $followUp->scheduled_at }}</td>
                  <td><span class="badge bg-secondary">{{ $followUp->status }}</span></td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-4">No follow-ups queued yet</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
          <strong>Sequence Library</strong>
        </div>
        <div class="card-body">
          @foreach ($presets as $name => $preset)
            <div class="border rounded-4 p-3 mb-3 bg-white">
              <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $name)) }}</div>
              <div class="text-muted small mb-1">{{ $preset['objective'] }}</div>
              <div class="small">Best for: {{ $preset['audience'] }}</div>
            </div>
          @endforeach
          <div class="alert alert-info mb-0">
            Use this page to plan reply-driven automation. Step 1 handles quick replies, Step 2 handles slower replies, and Step 3 is the follow-up.
          </div>
        </div>
      </div>

      <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
          <strong>Reply Verification</strong>
        </div>
        <div class="card-body">
          <div class="alert alert-info mb-3">
            Replies are verified by the WhatsApp webhook, matched to the contact phone number, and stamped with a reply path.
          </div>
          <ul class="small text-muted mb-0 ps-3">
            <li>Quick reply: verified within 120 minutes.</li>
            <li>Delayed reply: verified after 120 minutes.</li>
            <li>Verified path: the webhook confirmed the reply and saved it on the contact row.</li>
          </ul>
        </div>
      </div>

      <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white">
          <strong>How automation works</strong>
        </div>
        <div class="card-body">
          <ul class="small text-muted mb-0 ps-3">
            <li>Save a sequence plan.</li>
            <li>Incoming replies are matched to the active plan.</li>
            <li>Quick replies start at step 1, slower replies start at step 2.</li>
            <li>A background cron job sends the queued follow-ups automatically.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection
