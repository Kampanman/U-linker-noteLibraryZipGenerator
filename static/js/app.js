const App = () => {
    const [notes, setNotes] = React.useState([]);
    const [user, setUser] = React.useState(null);
    const [isMaster, setIsMaster] = React.useState(false);
    const [searchTerm, setSearchTerm] = React.useState('');
    const [currentPage, setCurrentPage] = React.useState(1);
    const [selectedCsvs, setSelectedCsvs] = React.useState({});
    const [isLoading, setIsLoading] = React.useState(false);
    const [isButtonDisabled, setIsButtonDisabled] = React.useState(false);
    const [isMasterButtonVisible, setIsMasterButtonVisible] = React.useState(true);

    const ITEMS_PER_PAGE = 10;

    React.useEffect(() => {
        // Check session and fetch initial data
        axios.get('../server/api/check_session.php')
            .then(res => {
                if (res.data.loggedIn) {
                    setUser(res.data.user);
                    setIsMaster(res.data.is_master || false);
                    fetchNoteList();
                } else {
                    window.location.href = 'auth.php';
                }
            })
            .catch(err => console.error('Session check failed:', err));
    }, []);

    const fetchNoteList = () => {
        axios.get('../server/api/get_note_list.php')
            .then(res => {
                if(res.data.success){
                    setNotes(res.data.data);
                    // Initialize selected state for CSVs
                    const initialSelection = {};
                    res.data.data.forEach(note => {
                        if(note.type === 'csv') {
                            initialSelection[note.name] = false;
                        }
                    });
                    setSelectedCsvs(initialSelection);
                } else {
                    alert(res.data.message);
                }
            })
            .catch(err => console.error('Failed to fetch note list:', err));
    };

    const handleLogout = () => {
        window.location.href = '../server/api/logout.php';
    };

    const handleCheckboxChange = (e) => {
        const { name, checked } = e.target;
        setSelectedCsvs(prev => ({ ...prev, [name]: checked }));
    };

    const handleDownload = () => {
        const selected = Object.keys(selectedCsvs).filter(key => selectedCsvs[key]);

        setIsLoading(true);
        setIsButtonDisabled(true);

        const params = new URLSearchParams();
        params.append('selected_csvs', JSON.stringify(selected));

        axios.post('../server/api/generate_zip.php', params, { responseType: 'blob' })
            .then(res => {
                const header = res.headers['content-disposition'];
                const parts = header.split(';');
                let filename = '';
                for(let i=0; i<parts.length; i++){
                    if(parts[i].trim().startsWith('filename=')){
                        filename = parts[i].split('=')[1].trim().replace(/"/g, '');
                        break;
                    }
                }
                if(!filename){
                    const date = new Date().toISOString().slice(0,10);
                    filename = `ulinker-notes-${date}.zip`;
                }

                const url = window.URL.createObjectURL(new Blob([res.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch(err => {
                // エラーレスポンスがblob形式の場合、テキストとして読み込んで表示する
                if (err.response && err.response.data instanceof Blob) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        try {
                            const errorData = JSON.parse(this.result);
                            console.error('Server error:', errorData);
                            alert(`サーバーエラー:\n${errorData.message || '詳細不明'}`);
                        } catch (e) {
                            const errorText = this.result;
                            if (errorText) {
                                console.error('Failed to parse error response:', errorText);
                                alert('Zipファイルの生成に失敗しました。サーバーからの応答を解析できません。詳細はコンソールを確認してください。');
                            }
                        }
                    }
                    reader.readAsText(err.response.data);
                } else {
                    console.error('Download failed:', err);
                    alert('Zipファイルの生成またはダウンロードに失敗しました。');
                }
            })
            .finally(() => {
                setIsLoading(false);
                // Keep button disabled after one successful click as per requirement
            });
    };

    const handleMasterDownload = () => {
        setIsMasterButtonVisible(false);
        setIsLoading(true);

        const selected = Object.keys(selectedCsvs).filter(key => selectedCsvs[key]);

        const params = new URLSearchParams();
        params.append('selected_csvs', JSON.stringify(selected));

        axios.post('../server/api/master_modify_notes.php', params, { responseType: 'blob' })
            .then(res => {
                const header = res.headers['content-disposition'];
                const parts = header.split(';');
                let filename = 'ulinker_notes__master_modified.zip'; // Default filename
                for(let i=0; i<parts.length; i++){
                    if(parts[i].trim().startsWith('filename=')){
                        filename = parts[i].split('=')[1].trim().replace(/"/g, '');
                        break;
                    }
                }
                
                const url = window.URL.createObjectURL(new Blob([res.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch(err => {
                if (err.response && err.response.data instanceof Blob) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        try {
                            const errorData = JSON.parse(this.result);
                            console.error('Server error:', errorData);
                            alert(`サーバーエラー:\n${errorData.message || '詳細不明'}`);
                        } catch (e) {
                            const errorText = this.result;
                            console.error('Master download failed. Server response:', errorText);
                            alert('処理に失敗しました。サーバーからの応答を解析できません。詳細はデベロッパーツール（F12キー）のコンソールを確認してください。');
                        }
                    }
                    reader.readAsText(err.response.data);
                } else {
                    console.error('Master download failed:', err);
                    alert('処理に失敗しました。');
                }
            })
            .finally(() => {
                setIsLoading(false);
            });
    };

    const filteredNotes = notes.filter(note => 
        note.name.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const paginatedNotes = filteredNotes.slice((currentPage - 1) * ITEMS_PER_PAGE, currentPage * ITEMS_PER_PAGE);
    const totalPages = Math.ceil(filteredNotes.length / ITEMS_PER_PAGE);

    return (
        <div className="app-container">
            {isLoading && <div className="loading-overlay">処理中...</div>}
            <header>
                <h1>{document.title}</h1>
                <button onClick={handleLogout}>ログアウト</button>
            </header>
            <main>
                <div className="table-container">
                    <div className="table-controls">
                        <input 
                            type="text" 
                            placeholder="検索..." 
                            value={searchTerm}
                            onChange={e => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ノート格納先</th>
                            </tr>
                        </thead>
                        <tbody>
                            {paginatedNotes.map(note => (
                                <tr key={note.name}>
                                    <td>
                                        {note.type === 'db' ? (
                                            <span>✓ {note.name} (固定)</span>
                                        ) : (
                                            <label>
                                                <input 
                                                    type="checkbox" 
                                                    name={note.name}
                                                    checked={selectedCsvs[note.name] || false}
                                                    onChange={handleCheckboxChange}
                                                /> 
                                                {note.name}
                                            </label>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {totalPages > 1 && (
                        <div className="pagination">
                            <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1}>前へ</button>
                            <span>ページ {currentPage} / {totalPages}</span>
                            <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages}>次へ</button>
                        </div>
                    )}
                    {isMaster && isMasterButtonVisible && (
                        <div className="master-action-container">
                            <button className="master-download-btn" onClick={handleMasterDownload}>
                                選択中のノートを整備してダウンロード
                            </button>
                        </div>
                    )}
                </div>
                <div className="footer-actions">
                    <p>ノート取得対象のCSVを選択してください</p>
                    <button className="download-btn" onClick={handleDownload} disabled={isButtonDisabled}>
                        選択中のノートを格納したZipをダウンロード
                    </button>
                </div>
            </main>
        </div>
    );
};

ReactDOM.render(<App />, document.getElementById('root'));
