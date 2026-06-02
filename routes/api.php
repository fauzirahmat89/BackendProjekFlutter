<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Models\Toko;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Hash;

// Authentication
Route::post('/register', function (Request $request) {
    $data = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|unique:users',
        'password' => 'required|string|min:6',
        'role' => 'required|in:toko,pembeli'
    ]);

    $data['password'] = Hash::make($data['password']);
    $user = User::create($data);
    
    return response()->json(['token' => $user->createToken('auth')->plainTextToken, 'user' => $user]);
});

Route::post('/login', function (Request $request) {
    $request->validate(['email' => 'required|email', 'password' => 'required']);
    $user = User::where('email', $request->email)->first();
    
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
    
    return response()->json(['token' => $user->createToken('auth')->plainTextToken, 'user' => $user]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'toko' => $request->user()->toko,
        ]);
    });

    // TOKO ENDPOINTS
    Route::prefix('toko')->group(function () {
        // Manage Toko
        Route::get('/', function (Request $request) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            return $request->user()->toko()->with('menus')->first();
        });
        
        Route::post('/', function (Request $request) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            $data = $request->validate([
                'name' => 'required|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'address' => 'nullable|string'
            ]);
            
            $toko = $request->user()->toko()->updateOrCreate(
                ['user_id' => $request->user()->id],
                $data
            );
            return response()->json($toko);
        });

        Route::delete('/', function (Request $request) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            if ($request->user()->toko) {
                $request->user()->toko()->delete();
            }
            return response()->json(['message' => 'Toko deleted']);
        });

        // Manage Menus
        Route::post('/menus', function (Request $request) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            $toko = $request->user()->toko;
            if (!$toko) return response()->json(['message' => 'Create toko first'], 400);
            
            $data = $request->validate([
                'name' => 'required|string',
                'price' => 'required|numeric',
                'description' => 'nullable|string',
                'image' => 'nullable|string'
            ]);
            
            $menu = $toko->menus()->create($data);
            return response()->json($menu);
        });

        Route::put('/menus/{menu}', function (Request $request, Menu $menu) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            if ($menu->toko_id !== $request->user()->toko->id) return response()->json(['message' => 'Unauthorized'], 403);
            
            $data = $request->validate([
                'name' => 'string',
                'price' => 'numeric',
                'description' => 'nullable|string',
                'image' => 'nullable|string'
            ]);
            
            $menu->update($data);
            return response()->json($menu);
        });

        Route::delete('/menus/{menu}', function (Request $request, Menu $menu) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            if ($menu->toko_id !== $request->user()->toko->id) return response()->json(['message' => 'Unauthorized'], 403);
            $menu->delete();
            return response()->json(['message' => 'Menu deleted']);
        });

        // TOKO ORDER MANAGEMENT
        Route::get('/orders', function (Request $request) {
            if ($request->user()->role !== 'toko') return response()->json(['message' => 'Unauthorized'], 403);
            return $request->user()->toko->orders()->with('user', 'orderItems.menu')->get();
        });

        Route::put('/orders/{order}', function (Request $request, Order $order) {
            if ($request->user()->role !== 'toko' || $order->toko_id !== $request->user()->toko->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $data = $request->validate(['status' => 'required|in:pending,processing,completed,cancelled']);
            $order->update($data);
            return response()->json($order);
        });
    });

    // PEMBELI ENDPOINTS
    Route::prefix('pembeli')->group(function () {
        Route::get('/tokos', function (Request $request) {
            if ($request->user()->role !== 'pembeli') return response()->json(['message' => 'Unauthorized'], 403);
            return Toko::with('menus')->get();
        });
        
        Route::get('/tokos/{toko}', function (Request $request, Toko $toko) {
            if ($request->user()->role !== 'pembeli') return response()->json(['message' => 'Unauthorized'], 403);
            return $toko->load('menus');
        });

        Route::post('/orders', function (Request $request) {
            if ($request->user()->role !== 'pembeli') return response()->json(['message' => 'Unauthorized'], 403);
            $data = $request->validate([
                'toko_id' => 'required|exists:tokos,id',
                'items' => 'required|array',
                'items.*.menu_id' => 'required|exists:menus,id',
                'items.*.quantity' => 'required|integer|min:1'
            ]);
            
            $total_price = 0;
            $orderItems = [];
            
            foreach ($data['items'] as $item) {
                $menu = Menu::find($item['menu_id']);
                if ($menu->toko_id != $data['toko_id']) {
                    return response()->json(['message' => 'Menu does not belong to toko'], 400);
                }
                $price = $menu->price * $item['quantity'];
                $total_price += $price;
                
                $orderItems[] = [
                    'menu_id' => $menu->id,
                    'quantity' => $item['quantity'],
                    'price' => $menu->price,
                ];
            }
            
            $order = $request->user()->orders()->create([
                'toko_id' => $data['toko_id'],
                'total_price' => $total_price,
                'status' => 'pending'
            ]);
            
            foreach ($orderItems as $item) {
                $order->orderItems()->create($item);
            }
            
            return response()->json($order->load('orderItems.menu'));
        });

        Route::get('/orders', function (Request $request) {
            if ($request->user()->role !== 'pembeli') return response()->json(['message' => 'Unauthorized'], 403);
            return $request->user()->orders()->with('toko', 'orderItems.menu')->get();
        });
    });
});