## 実装内容
- pagerを作成してメモリとファイルを効率的に扱う
- 実際のファイルにデータを保存してデータを永続化する


## 気がつきポイント
- fseekをしてしまうとその後の対象ファイルを参照する際にポインタの位置がずれてしまう
- ファイルの特定はまだみたい
- サイトのコードを見る感じ、ファイルを開く際にすべてのpageをnullで登録しているため、再度ログインした際にデータが取れる理由がわからない
- 固定長での登録だからやりやすいけど、可変長は難しそう
- ファイルを分割したくなってきた
- 今現在のコードでは、DBを繋いでいる際はメモリ（PHPの配列）にデータを保存して、そこからデータを取得し、DBを閉じる際にPHPの配列の内容をすべてファイルに移動させる
  - 世の中のDBもそうなのかな。。
    - DBを閉じる時 = DBとコネクションがなくなる時？