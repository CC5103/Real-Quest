<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTag;
use App\Models\Acceptance;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::orderBy('updated_at', 'desc')->get();
        return view('post.index', compact('posts'));
    }

    public function create()
    {
        return view('post.create');
    }

    // 投稿作成用
    public function store(Request $request)
    {
        logger("test");
        $validatedData = $request->validate([
            'title' => 'required|string|max:20',
            'content' => 'required|string|max:200',
            'reward' => 'required|integer',
            'tag_id' => 'required|integer',
            'address' => 'required|string',
            'deadline' => [
                'required',
                'date',
                'after:' . now()->addMinutes(4)->format('Y-m-d H:i:s'),
            ],
        ]);

        $post = new Post();
        $post->title = $validatedData['title'];
        $post->content = $validatedData['content'];
        $post->reward = $validatedData['reward'];
        $post->deadline = $validatedData['deadline'];
        $post->address = $validatedData['address'];
        $post->user_id = Auth::id();
        $post->is_completed = 0;
        $post->save();

        $posttag = new PostTag();
        $posttag->tag_id = $validatedData['tag_id'];
        $posttag->post_id = $post->id;
        $posttag->save();

        return redirect()->route('myposts')->with('success', '投稿が作成されました');
    }

    // home.blade.phpの投稿一覧表示用
    public function allPosts()
    {
        $posts = Post::where('is_completed', 0)->orderBy('updated_at', 'desc')->get();
        $address = Post::where('is_completed', 0)->orderBy('updated_at', 'desc')->pluck('address');
        $posts_id = Post::where('is_completed', 0)->orderBy('updated_at', 'desc')->pluck('id');
        $posttag = PostTag::whereIn('post_id', $posts_id)->orderBy('updated_at', 'desc')->pluck('tag_id');
        $acceptance = Acceptance::pluck('post_id');

        $combined = array_map(null, $posts->toArray(), $posttag->toArray());

        return view('home', [
            'combined' => $combined,
            'address' => $address,
            'acceptance' => $acceptance,
        ]);
    }

    public function myPosts()
    {
        $posts = Post::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->get();
        $postsAccepting = Post::where('user_id', Auth::id())->doesntHave('acceptance')->where('is_completed', False)->orderBy('updated_at', 'desc')->get();
        $postsOngoing = Post::where('user_id', Auth::id())->has('acceptance')->where('is_completed', False)->orderBy('updated_at', 'desc')->get();
        $postsCompleted = Post::where('user_id', Auth::id())->has('acceptance')->where('is_completed', True)->orderBy('updated_at', 'desc')->get();
        return view('my-posts', compact('posts', 'postsAccepting', 'postsOngoing', 'postsCompleted'));
    }

    public function myAccepteds()
    {
        $accepteds = Acceptance::where('user_id', Auth::id())->get();
        $acceptedsOngoing = Acceptance::where('user_id', Auth::id())->where('is_completed', False)->orderBy('updated_at', 'desc')->get();
        $acceptedsCompleted = Acceptance::where('user_id', Auth::id())->where('is_completed', True)->orderBy('updated_at', 'desc')->get();
        return view('my-accepteds', compact('accepteds', 'acceptedsOngoing', 'acceptedsCompleted'));
    }

    public function edit($id)
    {
        $post = Post::findOrFail($id);
        if ($post->acceptance) {
            $posttag = PostTag::where('post_id', $post->id)->pluck('tag_id');
            $tag = Tag::whereIn('id', $posttag)->first();
            return view('post.ongoing', compact('post', 'tag'));
        } else {
            $posttag = PostTag::where('post_id', $post->id)->first('tag_id');
            return view('post.edit', compact('post', 'posttag'));
        }
    }

    public function ongoing2($id)
    {
        $post = Post::findOrFail($id);
        $posttag = PostTag::where('post_id', $post->id)->pluck('tag_id');
        $tag = Tag::whereIn('id', $posttag)->first();
        return view('post.ongoing2', compact('post', 'tag'));
    }

    // 投稿詳細表示用
    public function detail($id)
    {
        $post = Post::findOrFail($id);
        $posttag = PostTag::where('post_id', $id)->pluck('tag_id');
        $tag = Tag::whereIn('id', $posttag)->first();
        if ($post->acceptance) {
            return view('post.acceptanceDetails', compact('post', 'tag'));
        } else {
            // $acceptance = Acceptance::find($id);
            // if ($acceptance) {
            //     return $acceptance;
            // } else {
            //     return false;
            // }
            return view('post.detail', [
                'post' => $post,
                'tag' => $tag
            ]);
        }
    }

    public function detail2($id)
    {
        $post = Post::findOrFail($id);
        $posttag = PostTag::where('post_id', $id)->pluck('tag_id');
        $tag = Tag::whereIn('id', $posttag)->first();
        if ($post->acceptance) {
            return view('post.acceptanceDetails2', compact('post', 'tag'));
        } else {
            // $acceptance = Acceptance::find($id);
            // if ($acceptance) {
            //     return $acceptance;
            // } else {
            //     return false;
            // }
            return view('post.detail', [
                'post' => $post,
                'tag' => $tag
            ]);
        }
    }

    // 受諾処理
    public function acceptance($id)
    {
        $acceptance = new Acceptance();
        $acceptance->is_completed = 0;
        $acceptance->user_id = Auth::id();
        $acceptance->post_id = $id;
        $acceptance->save();

        return redirect()->route('myaccepteds')->with('success', '投稿を受諾しました');
    }

    public function update(Request $request, $id)
    {
        logger($id);

        $validatedData = $request->validate([
            // 'title' => 'required|string|max:10',
            // 'content' => 'required|string|max:200',
            // 'reward' => 'required|integer',
            // 'tag_name' => 'required|string|in:option1,option2,option3',
            // 'address' => 'required|string',
            // 'deadline' => 'required|date',

            'title' => 'required|string|max:20',
            'content' => 'required|string|max:200',
            'reward' => 'required|integer',
            'tag_id' => 'integer',
            'address' => 'required|string',
            'deadline' => [
                'required',
                'date',
                'after:' . now()->addMinutes(4)->format('Y-m-d H:i:s'),
            ]
        ]);

        logger("test");

        $post = Post::where('id', $id)->first();
        $post->title = $validatedData['title'];
        $post->content = $validatedData['content'];
        $post->reward = $validatedData['reward'];
        $post->deadline = $validatedData['deadline'];
        $post->address = $validatedData['address'];
        $post->save();

        $posttag = PostTag::where('post_id', $id)->first();
        $posttag->tag_id = $validatedData['tag_id'];
        $posttag->save();

        return redirect()->route('myposts')->with('success', '投稿が更新されました');
    }

    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return redirect()->route('myposts')->with('success', '投稿が削除されました');
    }

    public function markAsComplete($id)
    {
        logger("test");
        $post = Post::findOrFail($id);
        $post->is_completed = True;
        $post->save();

        $acceptance = Acceptance::where('post_id', $id)->first();
        if ($acceptance->is_completed == 1) {
            return redirect()->route('myposts')->with('success', '依頼が達成されました!');
        } else {
            return redirect()->route('myposts')->with('success', '依頼が達成されました!');
        }
        // $acceptance->is_completed = True;
        // $acceptance->save();
        // return redirect()->route('myposts')->with('success', '依頼が達成されました!');
    }

    public function markAsComplete2($id)
    {
        logger("test");
        // $post = Post::findOrFail($id);
        // $post->is_completed = True;
        // $post->save();

        $acceptance = Acceptance::where('post_id', $id)->first();
        $acceptance->is_completed = True;
        $acceptance->save();
        return redirect()->route('myaccepteds')->with('success', '依頼が達成されました!');
    }

    public function acceptanceDetails($id)
    {
        $post = Post::findOrFail($id);
        $posttag = PostTag::where('post_id', $id)->pluck('tag_id');
        $tag = Tag::whereIn('id', $posttag)->first();
        return view('post.acceptanceDetails', [
            'post' => $post,
            'tag' => $tag,
        ]);
    }
}
