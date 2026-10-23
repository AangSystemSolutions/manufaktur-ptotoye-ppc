<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesOrder;
use App\Models\BillOfMaterial;
use App\Models\Inventory;
use App\Models\ProductionOrder;
use App\Models\PurchaseRequisition;
use App\Models\ShopFloorLog;
use App\Models\QualityControl;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    // Menu 1: Input Sales Order & Generate BOM / Draft SPK
    public function storeSalesOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $so = SalesOrder::create([
                'order_no' => 'SO-' . time(),
                'customer_name' => $request->customer_name,
                'product_name' => $request->product_name,
                'quantity' => $request->quantity,
                'drawing_spec' => $request->drawing_spec,
                'status' => 'Draft'
            ]);

            // Auto-create Draft SPK
            ProductionOrder::create([
                'sales_order_id' => $so->id,
                'spk_no' => 'SPK-' . time(),
                'status' => 'Draft'
            ]);

            DB::commit();
            return response()->json(['message' => 'Sales Order & Draft SPK berhasil dibuat!', 'data' => $so]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Menu 2: Inventory & MRP Check Stock
    public function runMRP($salesOrderId)
    {
        $so = SalesOrder::with('boms')->findOrFail($salesOrderId);
        $allStockSufficient = true;

        foreach ($so->boms as $bom) {
            $inventory = Inventory::where('item_code', $bom->material_code)->first();

            if (!$inventory || $inventory->stock_qty < $bom->required_qty) {
                $allStockSufficient = false;
                // Terbitkan Purchase Requisition (PR) jika kurang
                PurchaseRequisition::create([
                    'sales_order_id' => $so->id,
                    'material_code' => $bom->material_code,
                    'shortage_qty' => $bom->required_qty - ($inventory ? $inventory->stock_qty : 0),
                    'status' => 'Pending'
                ]);
            }
        }

        $prodOrder = ProductionOrder::where('sales_order_id', $so->id)->first();

        if ($allStockSufficient) {
            // Lock Stock (Allocated)
            foreach ($so->boms as $bom) {
                $inv = Inventory::where('item_code', $bom->material_code)->first();
                $inv->stock_qty -= $bom->required_qty;
                $inv->allocated_qty += $bom->required_qty;
                $inv->save();
            }
            $so->update(['status' => 'Ready to Schedule']);
            if ($prodOrder) $prodOrder->update(['status' => 'Ready to Schedule']);
            
            return response()->json(['message' => 'Stok cukup! Material di-lock. Status: Ready to Schedule']);
        } else {
            return response()->json(['message' => 'Stok kurang! Purchase Requisition (PR) diterbitkan otomatis.']);
        }
    }

    // Menu 4: Shop Floor QR Scan Start/Stop
    public function scanShopFloor(Request $request)
    {
        $log = ShopFloorLog::create([
            'production_order_id' => $request->production_order_id,
            'operator_id' => $request->operator_id,
            'scan_type' => $request->scan_type, // START / STOP
            'qty_ok' => $request->qty_ok ?? 0,
            'qty_scrap' => $request->qty_scrap ?? 0,
            'downtime_minutes' => $request->downtime_minutes ?? 0,
        ]);

        return response()->json(['message' => 'Shop floor log tercatat secara real-time!', 'data' => $log]);
    }

    // Menu 6: Executive Dashboard Analytics
    public function dashboardAnalytics()
    {
        $totalOrders = SalesOrder::count();
        $completedOrders = SalesOrder::where('status', 'Completed')->count();
        $otifRate = $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
        $totalScrap = ShopFloorLog::sum('qty_scrap');
        $totalDowntime = ShopFloorLog::sum('downtime_minutes');

        return response()->json([
            'otif_percentage' => round($otifRate, 2),
            'total_scrap' => $totalScrap,
            'total_downtime_minutes' => $totalDowntime,
        ]);
    }
}