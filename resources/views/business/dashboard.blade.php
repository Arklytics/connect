@extends('layouts.business')

@section('title', 'Business Dashboard')

@section('content')
  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Total Contacts</div>
        <div class="card-body"><h5>{{ $totalContacts ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Open Leads</div>
        <div class="card-body"><h5>{{ $openLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Due Follow-Ups</div>
        <div class="card-body"><h5>{{ $dueFollowUps ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Won Leads</div>
        <div class="card-body"><h5>{{ $wonLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">Lost Leads</div>
        <div class="card-body"><h5>{{ $lostLeads ?? 0 }}</h5></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-dark bg-light shadow border border-success mb-3">
        <div class="card-header">WhatsApp Opt-In</div>
        <div class="card-body"><h5>{{ $whatsappOptedIn ?? 0 }}</h5></div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="card shadow mb-3">
        <div class="card-body">
          <h5 class="card-title">Connect workflow</h5>
          <p class="card-text mb-2">Import leads, tag them by stage, mark won or lost, and queue WhatsApp follow-up steps for later dispatch.</p>
          <a href="{{ route('business.contacts.index') }}" class="btn btn-success">Open Connect</a>
        </div>
      </div>
    </div>
  </div>
@endsection
