<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Karte;
use App\Models\User;
use App\Models\Tag;
use App\Events\TimelineUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class KarteController extends Controller
{
    /** @var Karte */
    protected $karte;
    /** @var Tag */
    protected $tag;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Karte $karte, Tag $tag)
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });

        $this->karte = $karte;
        $this->tag = $tag;
    }


    /**
     * カルテの一覧を取得
     *
     * @param  \App\Models\User  $user  カルテを取得するユーザー
     * @return \Illuminate\Http\Response
     */
    public function index(User $user = null)
    {
        if (empty($user)) {
            // 全ユーザーのカルテ一覧
            $kartes = $this->karte->orderBy('created_at', 'desc')->get();
        } else {
            // 指定したユーザーのカルテ一覧
            $kartes = $user->kartes->sortByDesc('created_at')->values();
        }

        return response()->json($kartes);
    }

    /**
     * カルテの作成
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = $this->user->id;

        // 画像ファイル名の取得
        if (!empty($request->file('image'))) {
            // 現状では画像を1枚に制限
            $filename = $request->file('image')->hashName();
            $data['image'] = [$filename];
        }

        $result = $this->karte->create($data);

        if (empty($result)) {
            return response()->json(['message' => 'カルテの保存に失敗しました。'], config('consts.status.INTERNAL_SERVER_ERROR'));
        }

        // 画像ファイルの保存（カルテの作成日時を使用するためcreate後に実行）
        if (!empty($request->file('image'))) {
            $dir = config('consts.storage.karte') . $this->user->username . '/' . (new Carbon($result->created_at))->format('Y_md_Hi');
            $request->file('image')->storeAs($dir, $filename);
        }

        // タグの紐付け
        if (!empty($data['tags'])) {
            $result->tags()->sync(explode(',', $data['tags']));
        }

        broadcast(new TimelineUpdated($result));
        return response()->json(['message' => 'カルテが保存されました。']);
    }
}
