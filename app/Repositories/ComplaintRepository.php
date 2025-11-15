<?php
namespace App\Repositories;
use App\Models\Complaint;

class ComplaintRepository {
    public function createComplaint($data){
        return Complaint::create($data);
    }

    public function findById($id){
        return Complaint::find($id);
    }

    public function updateStatus($complaint, $status){
        $old = $complaint->status;
        $complaint->update(['status'=>$status]);
        $complaint->logs()->create([
            'user_id'=>auth()->id(),
            'action'=>'status_changed',
            'old_value'=>['status'=>$old],
            'new_value'=>['status'=>$status]
        ]);
        return $complaint;
    }

    public function lock($complaint){
        if($complaint->is_locked && $complaint->locked_by !== auth()->id()){
            return false;
        }
        $complaint->update([
            'is_locked'=>true,
            'locked_by'=>auth()->id(),
            'locked_at'=>now()
        ]);
        return true;
    }

    public function unlock($complaint){
        if($complaint->locked_by === auth()->id()){
            $complaint->update(['is_locked'=>false,'locked_by'=>null,'locked_at'=>null]);
        }
        return $complaint;
    }
}
