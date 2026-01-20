<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenants\TenantTickets;
use App\Models\Tenants\TenantTicketReply;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /**
     * Display a listing of my tickets.
     */
    public function index()
    {
        $user = Auth::guard('customer')->user();

        $tickets = TenantTickets::where('client_type', 'user')
            ->where('client_id', $user->id)
            ->latest()
            ->paginate(10);

        return Inertia::render('Customer/Tickets', [
            'tickets' => $tickets,
        ]);
    }

    /**
     * Store a new ticket.
     */
    public function store(Request $request)
    {
        $user = Auth::guard('customer')->user();

        $request->validate([
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string|max:2000',
        ]);

        // Check for existing open ticket
        $existing = TenantTickets::where('client_type', 'user')
            ->where('client_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return back()->withErrors(['error' => 'You already have an open ticket. Please update that ticket instead.']);
        }

        TenantTickets::create([
            'client_type' => 'user', // Maps to NetworkUser in morphMap
            'client_id' => $user->id,
            'priority' => $request->priority,
            'description' => $request->description,
            'status' => 'open',
            'created_by' => 0, // 0 indicates customer created it
        ]);

        return back()->with('success', 'Ticket created successfully.');
    }

    /**
     * Display the specified ticket with replies.
     */
    public function show(TenantTickets $ticket)
    {
        $user = Auth::guard('customer')->user();
        
        // Ensure ticket belongs to user
        if ($ticket->client_type !== 'user' || $ticket->client_id !== $user->id) {
            abort(403);
        }

        $ticket->load(['replies.repliable']);

        return Inertia::render('Customer/TicketShow', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Post a reply to the ticket.
     */
    public function reply(Request $request, TenantTickets $ticket)
    {
        $user = Auth::guard('customer')->user();

        // Ensure ticket belongs to user and is open
        if ($ticket->client_type !== 'user' || $ticket->client_id !== $user->id) {
            abort(403);
        }

        if ($ticket->status === 'closed') {
            return back()->withErrors(['error' => 'This ticket is closed.']);
        }

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        TenantTicketReply::create([
            'ticket_id' => $ticket->id,
            'repliable_type' => 'user', // Maps to NetworkUser in morphMap
            'repliable_id' => $user->id,
            'message' => $request->message,
        ]);

        return back()->with('success', 'Reply posted.');
    }
}
